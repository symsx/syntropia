#!/usr/bin/env python3
"""Convertit un outline markdown en fichier .minder (Minder, GNOME).

Convention attendue en entree :
  # Titre            -> noeud racine (une carte ; plusieurs "# " = plusieurs cartes)
  > note             -> juste apres le titre, avant toute puce : note de la racine
  - Branche          -> noeud enfant (2 espaces d'indentation par niveau)
    - Sous-branche
    > note           -> note du noeud de la puce juste au-dessus (meme indentation + 2)

Usage:
  md_to_minder.py entree.md [sortie.minder]
Sans fichier de sortie, ecrit sur stdout.

Note : la hierarchie et les notes sont preservees fidelement. La position
des noeuds (posx/posy) est calculee par une disposition simple ; si le
rendu visuel n'est pas ideal a l'ouverture dans Minder, reorganiser les
noeuds a la souris (le contenu, lui, est complet et correct).
"""
import re
import sys
import xml.etree.ElementTree as ET

STYLE_ATTRS = {
    "branchmargin": "86",
    "branchradius": "25",
    "linktype": "curved",
    "linkwidth": "5",
    "linkarrow": "false",
    "linkdash": "solid",
    "nodeborder": "rounded",
    "nodewidth": "200",
    "nodeborderwidth": "3",
    "nodefill": "false",
    "nodemargin": "8",
    "nodepadding": "6",
    "nodefont": "Sans 11",
    "nodemarkup": "true",
    "connectiondash": "dotted",
    "connectionlwidth": "2",
    "connectionarrow": "fromto",
    "connectionpadding": "3",
    "connectionfont": "Sans 10",
    "connectiontwidth": "100",
    "calloutfont": "Sans 12",
    "calloutpadding": "5",
    "calloutptrwidth": "20",
    "calloutptrlength": "20",
}

PALETTE = [
    "#f9c440", "#7a36b1", "#333333", "#c6262e",
    "#f37329", "#68b723", "#3689e6", "#e5a50a",
]

BULLET_RE = re.compile(r"^( *)- (.*)$")
NOTE_RE = re.compile(r"^( *)> ?(.*)$")
TITLE_RE = re.compile(r"^# (.*)$")


class Node:
    def __init__(self, title):
        self.title = title
        self.note_lines = []
        self.children = []

    @property
    def note(self):
        return "\n".join(self.note_lines).strip()


def parse_sections(lines):
    """Decoupe le markdown en une liste de (titre_racine, lignes_du_corps)."""
    sections = []
    current_title = None
    current_body = []
    for line in lines:
        m = TITLE_RE.match(line)
        if m:
            if current_title is not None:
                sections.append((current_title, current_body))
            current_title = m.group(1).strip()
            current_body = []
        else:
            current_body.append(line)
    if current_title is not None:
        sections.append((current_title, current_body))
    return sections


def parse_tree(title, body_lines):
    root = Node(title)
    stack = [(-1, root)]  # (depth, node) ; depth -1 = racine

    for line in body_lines:
        if not line.strip():
            continue

        bm = BULLET_RE.match(line)
        if bm:
            indent, text = bm.groups()
            depth = len(indent) // 2
            node = Node(text.strip())
            while stack and stack[-1][0] >= depth:
                stack.pop()
            parent = stack[-1][1]
            parent.children.append(node)
            stack.append((depth, node))
            continue

        nm = NOTE_RE.match(line)
        if nm:
            # la note s'attache au dernier noeud ouvert (racine ou puce)
            stack[-1][1].note_lines.append(nm.group(2))
            continue

    return root


def leaf_count(node):
    if not node.children:
        return 1
    return sum(leaf_count(c) for c in node.children)


_id_counter = [0]


def build_xml(node, depth, y_cursor, color=None):
    """Construit recursivement l'element <node> et renvoie (element, posy)."""
    node_id = _id_counter[0]
    _id_counter[0] += 1

    is_root = depth == 0
    branch_color = color
    if is_root:
        branch_color = None
    elif depth == 1:
        branch_color = PALETTE[node_id % len(PALETTE)]

    child_elems = []
    if node.children:
        y = y_cursor
        child_posys = []
        for child in node.children:
            elem, y = build_xml(child, depth + 1, y, branch_color)
            child_elems.append(elem)
            child_posys.append(float(elem.get("posy")))
        posy = sum(child_posys) / len(child_posys)
    else:
        posy = y_cursor
        y = y_cursor + 60

    width = str(min(200, max(60, len(node.title) * 7 + 20)))
    attrs = {
        "id": str(node_id),
        "posx": str(depth * 220),
        "posy": str(posy),
        "width": width,
        "height": "49",
        "side": "left" if is_root else "right",
        "fold": "false",
        "treesize": str(max(49, leaf_count(node) * 49)),
        "summarized": "false",
        "layout": "Horizontal",
        "group": "false",
    }
    if branch_color:
        attrs["color"] = branch_color
        attrs["colorroot"] = "false"

    node_elem = ET.Element("node", attrs)
    style_elem = ET.SubElement(node_elem, "style", dict(STYLE_ATTRS))
    nodename = ET.SubElement(node_elem, "nodename", {"maxwidth": "200"})
    ET.SubElement(nodename, "text", {"data": node.title})
    nodenote = ET.SubElement(node_elem, "nodenote")
    if node.note:
        nodenote.text = node.note

    if child_elems:
        nodes_elem = ET.SubElement(node_elem, "nodes")
        for elem in child_elems:
            nodes_elem.append(elem)

    return node_elem, y


def convert(md_text):
    lines = md_text.splitlines()
    sections = parse_sections(lines)
    if not sections:
        raise ValueError("Aucun titre '# ...' trouve dans le markdown.")

    minder = ET.Element("minder", {"version": "1.16.2", "etag": "0"})
    ET.SubElement(minder, "theme", {"name": "default", "label": "Light", "index": "-1"})
    styles = ET.SubElement(minder, "styles")
    for level in range(11):
        attrs = {"level": str(level), "isset": "true"}
        attrs.update(STYLE_ATTRS)
        ET.SubElement(styles, "style", attrs)
    ET.SubElement(minder, "images")
    nodes_root = ET.SubElement(minder, "nodes")

    y_cursor = 0.0
    for title, body in sections:
        tree_node = parse_tree(title, body)
        elem, y_cursor = build_xml(tree_node, 0, y_cursor)
        nodes_root.append(elem)
        y_cursor += 40

    ET.indent(minder, space="  ")
    xml_body = ET.tostring(minder, encoding="unicode")
    return '<?xml version="1.0"?>\n' + xml_body + "\n"


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    with open(sys.argv[1], "r", encoding="utf-8") as f:
        md_text = f.read()

    xml_out = convert(md_text)

    if len(sys.argv) >= 3:
        with open(sys.argv[2], "w", encoding="utf-8") as f:
            f.write(xml_out)
    else:
        sys.stdout.write(xml_out)


if __name__ == "__main__":
    main()
