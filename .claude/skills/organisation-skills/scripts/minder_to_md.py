#!/usr/bin/env python3
"""Convertit un fichier .minder (Minder, GNOME) en outline markdown.

Convention de sortie :
  # Titre            -> noeud racine
  - Branche          -> noeud enfant (2 espaces d'indentation par niveau)
    - Sous-branche
  > note             -> note du noeud juste au-dessus (meme indentation + "> ")

Usage:
  minder_to_md.py entree.minder [sortie.md]
Sans fichier de sortie, ecrit sur stdout.
"""
import sys
import xml.etree.ElementTree as ET


def get_title(node):
    nodename = node.find("nodename")
    if nodename is None:
        return ""
    text = nodename.find("text")
    return text.get("data", "") if text is not None else ""


def get_note(node):
    nodenote = node.find("nodenote")
    if nodenote is None or nodenote.text is None:
        return ""
    return nodenote.text.strip()


def render(node, depth):
    lines = []
    title = get_title(node)
    note = get_note(node)

    if depth == 0:
        lines.append(f"# {title}")
        if note:
            lines.append("")
            lines.extend(f"> {l}" for l in note.splitlines())
        lines.append("")
    else:
        indent = "  " * (depth - 1)
        lines.append(f"{indent}- {title}")
        if note:
            note_indent = "  " * depth
            lines.extend(f"{note_indent}> {l}" for l in note.splitlines())

    children = node.find("nodes")
    if children is not None:
        for child in children.findall("node"):
            lines.extend(render(child, depth + 1))
    return lines


def convert(minder_path):
    tree = ET.parse(minder_path)
    root = tree.getroot()
    top_nodes = root.find("nodes")
    if top_nodes is None:
        raise ValueError("Aucun element <nodes> trouve : fichier .minder invalide.")

    out_lines = []
    for i, node in enumerate(top_nodes.findall("node")):
        if i > 0:
            out_lines.append("")
        out_lines.extend(render(node, 0))
    return "\n".join(out_lines).rstrip() + "\n"


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    md = convert(sys.argv[1])

    if len(sys.argv) >= 3:
        with open(sys.argv[2], "w", encoding="utf-8") as f:
            f.write(md)
    else:
        sys.stdout.write(md)


if __name__ == "__main__":
    main()
