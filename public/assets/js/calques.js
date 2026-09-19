/* La carte à calques — dessiner sur le terrain, sans bibliothèque.
 *
 * Porté de `application/public/assets/calques.js` (dépôt de référence,
 * 785 lignes), logique de dessin/état/rendu inchangée. Ce qui change,
 * pour tenir sur le gabarit « le bureau » au lieu de la coquille propre
 * à `carte.php` :
 *
 *   - Les modes de dessin (déplacer/point/ligne/polygone/libre) sont le
 *     bandeau d'outils partagé (`name="outil"`, `Gabarit.onOutilChange()`)
 *     plutôt qu'un groupe de radios `name="mode"` réinventé ici.
 *   - `nommerLesModes()` ne renomme plus le texte des outils selon le
 *     calque choisi (« une ligne » → « une flèche » pour les vents) —
 *     ce texte vient du bandeau d'outils partagé, commun à toute page.
 *     Elle continue à atténuer (`.mode--autre`) les outils que le
 *     calque actif n'attend pas, avec l'info-bulle qui dit pourquoi.
 *     Simplification assumée, pas un oubli.
 *   - Les messages ponctuels (enregistré, erreur…) passent par la bulle
 *     du gabarit (`Gabarit.bulle`) plutôt qu'un paragraphe dédié.
 *   - Le cadastre (Phase 6) et la recherche d'eau (Phase 7) sont gérés
 *     par le code ci-dessous exactement comme la référence — le pari
 *     pris en Phase 5 (garder ce code tant que `cfg.apiCadastre`/
 *     `cfg.apiEau` n'existent pas, et qu'il ne trouve donc rien à
 *     accrocher, `$('cadastre-visible')` etc. renvoyant `null`) a payé
 *     aux deux phases suivantes : ni l'une ni l'autre n'a eu besoin de
 *     toucher une seule ligne de ce fichier.
 *
 * Une couche SVG posée sur les tuiles de `carte.js`, qui expose sa
 * projection (`conteneur.carte`) et prévient quand la vue change
 * (`carte:vue`). Ici : les éléments enregistrés, le tracé en cours, la
 * pose d'une parcelle par ses dimensions, l'échelle.
 *
 * Tout ce qui est calculé en mètres — surface, longueur, rectangle d'une
 * parcelle — passe par une projection locale plane : à l'échelle d'un
 * terrain, l'erreur est négligeable. La même formule est dans `Calques.php`.
 */
(function () {
  'use strict';

  var conteneur = document.querySelector('[data-carte][data-calques]');
  if (!conteneur) { return; }
  var cfg = JSON.parse(conteneur.dataset.calques);
  var NS = 'http://www.w3.org/2000/svg';

  // Le zoom d'arrivée : le plus fin que servent les tuiles d'OpenStreetMap,
  // soit environ 40 cm par point d'écran sous nos latitudes. C'est la limite
  // de la photo aérienne, et elle décide de ce que cette carte sait faire :
  // poser une parcelle sur le terrain, non dessiner ce qu'il y a dedans.
  var ZOOM_TERRAIN = 18;

  var svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('class', 'calques');
  conteneur.appendChild(svg);

  var elements = cfg.elements.slice();
  var visibles = {};
  Object.keys(cfg.calques).forEach(function (c) { visibles[c] = true; });

  /* LE CADASTRE — un repère, non une donnée du projet. Voir l'en-tête :
   * inerte tant que la Phase 6 n'a pas posé le bouton et `cfg.apiCadastre`. */
  var cadastre = { visible: false, parcelles: [], message: '', minuteur: null, encours: false };

  var mode = 'deplacer';          // deplacer · point · ligne · polygone · libre · rectangle · etiquette · repositionner
  var selection = null;           // { id } — la parcelle qu'on repositionne
  var previsu = null;             // { id, angle, points } — son orientation, avant enregistrement
  var enCours = null;             // { calque, type, points: [[lat, lon]] }
  var rect = null;                // { parcelle, centre, angle, largeur, longueur }
  var enLibre = false;

  function $(id) { return document.getElementById(id); }
  function texte(id, t) { var el = $(id); if (el) { el.textContent = t; } }

  // ---------------------------------------------------------------- mètres
  function metresParDegre(lat) {
    return [111320, 111320 * Math.cos(lat * Math.PI / 180)];
  }
  function enMetres(points) {
    if (!points.length) { return []; }
    var o = points[0], m = metresParDegre(o[0]);
    return points.map(function (p) { return [(p[1] - o[1]) * m[1], (p[0] - o[0]) * m[0]]; });
  }
  function surface(points) {
    if (points.length < 3) { return 0; }
    var xy = enMetres(points), a = 0;
    for (var i = 0; i < xy.length; i++) {
      var p = xy[i], q = xy[(i + 1) % xy.length];
      a += p[0] * q[1] - q[0] * p[1];
    }
    return Math.abs(a) / 2;
  }
  function longueur(points) {
    var xy = enMetres(points), l = 0;
    for (var i = 1; i < xy.length; i++) {
      l += Math.hypot(xy[i][0] - xy[i - 1][0], xy[i][1] - xy[i - 1][1]);
    }
    return l;
  }
  function centre(points) {
    var la = 0, lo = 0;
    points.forEach(function (p) { la += p[0]; lo += p[1]; });
    return [la / points.length, lo / points.length];
  }
  function arrondi(v, d) { return Math.round(v * Math.pow(10, d)) / Math.pow(10, d); }
  function fmt(m) { return m >= 10000 ? arrondi(m / 10000, 2) + ' ha' : arrondi(m, 0) + ' m²'; }

  /* Les quatre coins d'une parcelle posée par ses dimensions : longueur
   * d'est en ouest, largeur du sud au nord — mêmes noms que « Longueur
   * (est ↔ ouest) »/« Largeur (nord ↔ sud) » de conception-parcelles.php,
   * pas l'inverse (bug trouvé le 2026-09-18 : une parcelle de 17 x 4 m
   * se posait tournée de 90°, largeur et longueur confondues) — puis
   * rotation d'un angle compté depuis le nord, dans le sens des
   * aiguilles d'une montre. */
  function coins(r) {
    var m = metresParDegre(r.centre[0]);
    var a = r.angle * Math.PI / 180, ca = Math.cos(a), sa = Math.sin(a);
    return [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(function (s) {
      var x = s[0] * r.longueur / 2, y = s[1] * r.largeur / 2;
      var xr = x * ca + y * sa, yr = -x * sa + y * ca;
      return [arrondi(r.centre[0] + yr / m[0], 5), arrondi(r.centre[1] + xr / m[1], 5)];
    });
  }

  // ---------------------------------------------------------------- dessin
  function px(p) { return conteneur.carte.versPixel(p[0], p[1]); }
  function noeud(nom, attrs, parent) {
    var n = document.createElementNS(NS, nom);
    Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    (parent || svg).appendChild(n);
    return n;
  }
  /* L'ÉTIQUETTE D'UN OBJET. Par défaut à huit points de son ancrage, en haut
   * à droite. Une étiquette qui gêne se déplace (outil « Étiquettes ») : sa
   * place est alors une position sur Terre, `proprietes.etiquette = [lat, lon]`,
   * et non un décalage en pixels — sans quoi elle glisserait au moindre zoom.
   * Quand elle s'éloigne de son objet, un trait pointillé les relie encore.
   * `el` n'est passé que pour les étiquettes qui appartiennent à un objet :
   * les altitudes des courbes, le cadastre et l'aperçu d'une parcelle
   * ne se déplacent pas. */
  var DECALAGE_ETIQUETTE = [8, -8];
  var ECART_GUIDE = 24;
  function etiquette(p, mot, teinte, el) {
    if (!mot) { return; }
    var q = px(p);
    var propre = el && el.proprietes && el.proprietes.etiquette;
    // Une position illisible (écrite à la main dans la base) ne fait jamais disparaître l'étiquette.
    if (propre && !(propre.length === 2 && isFinite(propre[0]) && isFinite(propre[1]))) { propre = null; }
    var o = propre ? px(propre) : [q[0] + DECALAGE_ETIQUETTE[0], q[1] + DECALAGE_ETIQUETTE[1]];
    if (propre && Math.hypot(o[0] - q[0], o[1] - q[1]) > ECART_GUIDE) {
      noeud('line', { x1: q[0], y1: q[1], x2: o[0], y2: o[1] - 4, stroke: teinte, 'stroke-width': 1.2,
        'stroke-opacity': 0.7, 'stroke-dasharray': '3 3', 'class': 'calque-guide', 'data-id': el.id });
    }
    var t = noeud('text', { x: o[0], y: o[1], fill: teinte,
      'class': 'calque-etiquette' + (el ? ' calque-etiquette--mobile' : '') });
    if (el) { t.setAttribute('data-id', el.id); t.setAttribute('data-ancre', q[0] + ',' + q[1]); }
    t.textContent = mot;
  }
  function chemin(points) {
    return points.map(function (p) { var q = px(p); return q[0] + ',' + q[1]; }).join(' ');
  }

  /* LE VENT SE TRACE AVEC UNE FLÈCHE, et son épaisseur dit son intensité.
   * Un vent dominant et une brise de vallée ne se lisent pas au même
   * poids de trait ; la carte doit les distinguer d'un coup d'œil. */
  var INTENSITES = {
    dominant: { libelle: 'dominant', trait: 6 },
    secondaire: { libelle: 'secondaire', trait: 2.5 }
  };
  function trait(el) {
    var i = el && el.proprietes && el.proprietes.intensite;
    return (INTENSITES[i] || INTENSITES.secondaire).trait;
  }

  /* La pointe, posée au dernier point et orientée par le dernier segment.
   * Elle grandit avec le trait : une flèche fine sur un trait épais se
   * verrait comme une bavure. */
  function pointe(points, teinte, largeur, parent) {
    if (points.length < 2) { return; }
    var b = px(points[points.length - 1]);
    var a = px(points[points.length - 2]);
    var dx = b[0] - a[0], dy = b[1] - a[1];
    var d = Math.hypot(dx, dy);
    if (d < 1) { return; }
    dx /= d; dy /= d;
    var l = largeur * 3.2, w = largeur * 1.6;
    var p1 = [b[0] - l * dx + w * dy, b[1] - l * dy - w * dx];
    var p2 = [b[0] - l * dx - w * dy, b[1] - l * dy + w * dx];
    noeud('polygon', { points: b[0] + ',' + b[1] + ' ' + p1[0] + ',' + p1[1]
      + ' ' + p2[0] + ',' + p2[1], fill: teinte }, parent);
  }

  function dessinerElement(el) {
    if (!visibles[el.calque] || !el.geometrie || !el.geometrie.length) { return; }
    var teinte = (cfg.calques[el.calque] || {}).teinte || '#333';
    var g = noeud('g', { 'class': 'calque calque--' + el.calque, 'data-id': el.id });
    if (el.type === 'point' || el.geometrie.length === 1) {
      var q = px(el.geometrie[0]);
      noeud('circle', { cx: q[0], cy: q[1], r: 6, fill: teinte, stroke: '#fff', 'stroke-width': 2 }, g);
      var h = el.proprietes && el.proprietes.hauteur_m ? ' · ' + el.proprietes.hauteur_m + ' m' : '';
      etiquette(el.geometrie[0], (el.libelle || '') + h, teinte, el);
    } else if (el.type === 'ligne') {
      var vent = el.calque === 'vents';
      var largeur = vent ? trait(el) : 3;
      noeud('polyline', { points: chemin(el.geometrie), fill: 'none', stroke: teinte,
        'stroke-width': largeur, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, g);
      if (vent) { pointe(el.geometrie, teinte, largeur, g); }
      var mot = el.libelle || '';
      if (vent && el.proprietes && el.proprietes.intensite === 'dominant') {
        mot = (mot ? mot + ' · ' : '') + 'dominant';
      }
      etiquette(el.geometrie[Math.floor(el.geometrie.length / 2)], mot, teinte, el);
    } else if (el.type === 'grille') {
      var a = px(el.geometrie[0]), b = px(el.geometrie[1]);
      noeud('rect', { x: Math.min(a[0], b[0]), y: Math.min(a[1], b[1]),
        width: Math.abs(b[0] - a[0]), height: Math.abs(b[1] - a[1]),
        fill: 'none', stroke: teinte, 'stroke-width': 1,
        'stroke-opacity': 0.35, 'stroke-dasharray': '2 6' }, g);
      var courbes = (el.proprietes && el.proprietes.courbes) || [];
      var parNiveau = {};
      courbes.forEach(function (c, i) {
        noeud('line', { x1: px(c.points[0])[0], y1: px(c.points[0])[1],
          x2: px(c.points[1])[0], y2: px(c.points[1])[1],
          stroke: teinte, 'stroke-width': 1.4, 'stroke-linecap': 'round' }, g);
        (parNiveau[c.altitude] = parNiveau[c.altitude] || []).push(i);
      });
      var niveaux = Object.keys(parNiveau);
      niveaux.forEach(function (altitude, rang) {
        if (niveaux.length > 8 && rang % 2) { return; }
        var siens = parNiveau[altitude];
        var milieu = courbes[siens[Math.floor(siens.length / 2)]];
        etiquette(milieu.points[0], Math.round(altitude) + ' m', teinte);
      });
      if (!courbes.length) { etiquette(el.geometrie[1], el.libelle, teinte); }
    } else if (el.type === 'polygone') {
      var parcelle = el.calque === 'parcelles';
      var pts = previsu && previsu.id === el.id ? previsu.points : el.geometrie;
      var choisie = parcelle && selection && selection.id === el.id;
      var poly = noeud('polygon', { points: chemin(pts), fill: teinte,
        'fill-opacity': parcelle ? (choisie ? 0.26 : 0.12) : 0.22,
        stroke: teinte, 'stroke-width': parcelle ? (choisie ? 4.5 : 3) : 2,
        'stroke-dasharray': parcelle ? '' : '6 4' }, g);
      if (parcelle) { poly.setAttribute('class', 'parcelle-mobile'); }
      etiquette(centre(pts), el.libelle, teinte, el);
    }
  }

  /* Le cadastre se dessine en premier, donc dessous : un trait fin, sans
   * remplissage, et le numéro de parcelle quand il y a la place. */
  function dessinerCadastre() {
    if (!cadastre.visible || !cadastre.parcelles.length) { return; }
    var g = noeud('g', { 'class': 'calque-cadastre' });
    cadastre.parcelles.forEach(function (p) {
      if (!p.points || p.points.length < 3) { return; }
      noeud('polygon', { points: chemin(p.points), fill: 'none', stroke: '#B4622A',
        'stroke-width': 1.2, 'stroke-opacity': 0.85 }, g);
      var a = px(p.points[0]), b = px(p.points[Math.floor(p.points.length / 2)]);
      if (Math.abs(a[0] - b[0]) + Math.abs(a[1] - b[1]) > 60) {
        var c = centre(p.points), q = px(c);
        var t = noeud('text', { x: q[0], y: q[1], fill: '#8A4718', 'text-anchor': 'middle',
          'class': 'calque-etiquette cadastre-numero' }, g);
        t.textContent = p.libelle;
      }
    });
  }

  function dessinerEnCours() {
    if (!enCours || !enCours.points.length) { return; }
    var teinte = (cfg.calques[enCours.calque] || {}).teinte || '#333';
    if (enCours.points.length > 1) {
      var attrs = { points: chemin(enCours.points), fill: 'none', stroke: teinte,
        'stroke-width': 2.5, 'stroke-dasharray': '4 3' };
      if (enCours.type === 'polygone' && enCours.points.length > 2) {
        attrs.fill = teinte; attrs['fill-opacity'] = 0.15;
        noeud('polygon', attrs);
      } else {
        noeud('polyline', attrs);
      }
    }
    if (enCours.calque === 'vents' && enCours.type === 'ligne') {
      pointe(enCours.points, teinte, intensiteChoisie().trait, null);
    }
    if (enCours.type !== 'polygone' || enCours.points.length < 40) {
      enCours.points.forEach(function (p) {
        var q = px(p);
        noeud('circle', { cx: q[0], cy: q[1], r: 4, fill: '#fff', stroke: teinte, 'stroke-width': 2 });
      });
    }
  }

  function dessinerRect() {
    if (!rect || !rect.centre) { return; }
    var teinte = cfg.calques.parcelles.teinte;
    var c = coins(rect);
    noeud('polygon', { points: chemin(c), fill: teinte, 'fill-opacity': 0.18, stroke: teinte,
      'stroke-width': 3, 'stroke-dasharray': '8 4' });
    etiquette(rect.centre, rect.parcelle.code + ' — ' + rect.largeur + ' × ' + rect.longueur + ' m', teinte);
    var q0 = px(c[3]), q1 = px(c[2]);
    noeud('line', { x1: q0[0], y1: q0[1], x2: q1[0], y2: q1[1], stroke: '#fff', 'stroke-width': 5, 'stroke-linecap': 'round' });
  }

  function echelle() {
    var barre = $('carte-echelle');
    if (!barre || !conteneur.carte) { return; }
    var mpp = conteneur.carte.metresParPixel();
    var choix = [1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000], m = choix[0];
    choix.forEach(function (c) { if (c / mpp <= 180) { m = c; } });
    barre.style.width = (m / mpp) + 'px';
    barre.textContent = m >= 1000 ? (m / 1000) + ' km' : m + ' m';
  }

  function tout() {
    if (!conteneur.carte) { return; }
    while (svg.firstChild) { svg.removeChild(svg.firstChild); }
    svg.setAttribute('viewBox', '0 0 ' + conteneur.clientWidth + ' ' + conteneur.clientHeight);
    dessinerCadastre();
    elements.forEach(dessinerElement);
    dessinerEnCours();
    dessinerRect();
    echelle();
  }

  /* Demander les parcelles cadastrales de la vue — Phase 6, inerte pour
   * l'instant (voir en-tête). */
  function chargerCadastre() {
    if (!cadastre.visible || !conteneur.carte || cadastre.encours || !cfg.apiCadastre) { return; }
    var l = conteneur.clientWidth, h = conteneur.clientHeight;
    var so = conteneur.carte.versLatLon(0, h), ne = conteneur.carte.versLatLon(l, 0);
    cadastre.encours = true;
    fetch(cfg.apiCadastre, { method: 'POST', body: new URLSearchParams({
      csrf: cfg.csrf, site_id: cfg.siteId,
      sud: so[0], ouest: so[1], nord: ne[0], est: ne[1] }) })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        cadastre.encours = false;
        cadastre.parcelles = j.ok ? j.parcelles : [];
        cadastre.message = j.message || '';
        etatCadastre();
        tout();
      })
      .catch(function () {
        cadastre.encours = false;
        cadastre.parcelles = [];
        cadastre.message = "Le cadastre n'a pas répondu. Les limites se tracent à la main.";
        etatCadastre();
        tout();
      });
  }

  function cadastreBientot() {
    if (!cadastre.visible) { return; }
    window.clearTimeout(cadastre.minuteur);
    cadastre.minuteur = window.setTimeout(chargerCadastre, 500);
  }

  function etatCadastre() {
    var texteEtat = !cadastre.visible ? ''
      : (cadastre.parcelles.length
          ? cadastre.parcelles.length + ' parcelle' + (cadastre.parcelles.length > 1 ? 's' : '')
            + ' au cadastre — suivez leurs bords pour tracer vos limites.'
          : cadastre.message);
    var el = $('cadastre-etat');
    if (el) { el.textContent = texteEtat; }

    // Case cochée, rien à dessiner (pas encore de réponse, ou une réponse
    // vide — hors zoom, hors couverture…) : le petit texte du panneau
    // Données se manque facilement, surtout panneau fermé. Le même message
    // reparaît en bandeau sur la carte, là où le regard est déjà — même
    // traitement visuel que « le lieu n'est pas encore posé ».
    var banniere = $('carte-cadastre-info');
    if (!banniere) { return; }
    var visible = cadastre.visible && !cadastre.parcelles.length && cadastre.message !== '';
    banniere.hidden = !visible;
    banniere.textContent = visible ? cadastre.message : '';
  }

  // ---------------------------------------------------------------- listes
  function listes() {
    var ul = $('calques-liste');
    if (!ul) { return; }
    ul.textContent = '';
    Object.keys(cfg.calques).forEach(function (c) {
      var siens = elements.filter(function (e) { return e.calque === c; });
      var li = document.createElement('li');
      li.className = 'calque-ligne';
      var lab = document.createElement('label');
      var cb = document.createElement('input');
      cb.type = 'checkbox'; cb.className = 'case'; cb.checked = visibles[c];
      cb.addEventListener('change', function () { visibles[c] = cb.checked; tout(); });
      var pastille = document.createElement('span');
      pastille.className = 'calque-pastille'; pastille.style.background = cfg.calques[c].teinte;
      lab.append(cb, pastille, ' ' + cfg.calques[c].libelle + ' ');
      var n = document.createElement('small');
      n.textContent = siens.length ? siens.length + ' tracé' + (siens.length > 1 ? 's' : '') : 'rien';
      lab.appendChild(n);
      li.appendChild(lab);
      if (siens.length) {
        var sous = document.createElement('ul');
        siens.forEach(function (e) {
          var it = document.createElement('li');
          var mesure = e.type === 'polygone' ? fmt(surface(e.geometrie))
            : e.type === 'ligne' ? arrondi(longueur(e.geometrie), 0) + ' m' : '';
          if (e.proprietes && e.proprietes.intensite === 'dominant') { mesure += ', dominant'; }
          var a = document.createElement('a');
          a.href = '#'; a.textContent = e.libelle || (e.type + ' sans nom');
          a.addEventListener('click', function (ev) {
            ev.preventDefault();
            var c0 = centre(e.geometrie);
            conteneur.carte.centrer(c0[0], c0[1]);
          });
          it.append(a, mesure ? ' — ' + mesure : '');
          if (cfg.peutEcrire) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'discret'; b.textContent = 'retirer';
            b.addEventListener('click', function () { retirer(e.id); });
            it.append(' ', b);
          }
          sous.appendChild(it);
        });
        li.appendChild(sous);
      }
      ul.appendChild(li);
    });
    var sel = $('parcelle-choix');
    if (sel) {
      Array.prototype.forEach.call(sel.options, function (o) {
        if (!o.value) { return; }
        var posee = elements.some(function (e) { return e.calque === 'parcelles' && String(e.parcelle_id) === o.value; });
        o.textContent = o.dataset.nom + (posee ? ' — posée' : o.dataset.dims ? ' — ' + o.dataset.dims : ' — sans dimensions');
      });
    }
  }

  // ------------------------------------------------------- message ponctuel
  /* La bulle du gabarit, plutôt qu'un paragraphe dédié — voir l'en-tête. */
  var messageMinuteur = null;
  function message(m) {
    if (!m || !window.Gabarit || !window.Gabarit.bulle) { return; }
    var cible = $('trace-enregistrer') || conteneur;
    window.Gabarit.bulle.montrer(cible, m);
    window.clearTimeout(messageMinuteur);
    messageMinuteur = window.setTimeout(function () {
      window.Gabarit.bulle.cacher();
    }, 4000);
  }

  // ---------------------------------------------------------------- écrire
  function envoyer(champs) {
    var fd = new URLSearchParams(champs);
    fd.set('csrf', cfg.csrf); fd.set('site_id', cfg.siteId);
    return fetch(cfg.api, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
  }

  function enregistrer() {
    var libelle = ($('trace-libelle') || {}).value || '';
    var hauteur = parseFloat(($('trace-hauteur') || {}).value);
    var champs;
    if (rect && rect.centre) {
      champs = { action: 'ajouter', calque: 'parcelles', type: 'polygone', libelle: rect.parcelle.code,
        parcelle_id: rect.parcelle.id, geometrie: JSON.stringify(coins(rect)),
        proprietes: JSON.stringify({ angle: rect.angle, largeur_m: rect.largeur, longueur_m: rect.longueur,
          surface_m2: arrondi(rect.largeur * rect.longueur, 1), centre: rect.centre }) };
    } else if (enCours && enCours.points.length) {
      var props = {};
      if (!isNaN(hauteur)) { props.hauteur_m = hauteur; }
      if (enCours.calque === 'vents') {
        props.intensite = ($('trace-intensite') || { value: 'dominant' }).value;
      }
      champs = { action: 'ajouter', calque: enCours.calque, type: enCours.type, libelle: libelle,
        geometrie: JSON.stringify(enCours.points), proprietes: JSON.stringify(props) };
      if (enCours.calque === 'parcelles' && ($('parcelle-choix') || {}).value) {
        champs.parcelle_id = $('parcelle-choix').value;
      }
    } else {
      message('Rien à enregistrer : tracez d\'abord.'); return;
    }
    envoyer(champs).then(function (j) {
      if (!j.ok) { message(j.message); return; }
      elements.push(j.element);
      if (boutonCentrer) { boutonCentrer.hidden = false; }
      enCours = null; rect = null;
      if ($('trace-libelle')) { $('trace-libelle').value = ''; }
      etat(); tout(); listes(); message(j.message);
    }).catch(function () { message('Le serveur n\'a pas répondu — rien n\'est enregistré.'); });
  }

  function retirer(id) {
    envoyer({ action: 'retirer', id: id }).then(function (j) {
      if (!j.ok) { message(j.message); return; }
      elements = elements.filter(function (e) { return String(e.id) !== String(id); });
      tout(); listes(); message(j.message);
    });
  }

  // ---------------------------------------------------------------- tracer
  var presse = null;
  function latlon(e) {
    var r = conteneur.getBoundingClientRect();
    var p = conteneur.carte.versLatLon(e.clientX - r.left, e.clientY - r.top);
    return [arrondi(p[0], 5), arrondi(p[1], 5)];
  }
  function nouveau(type) {
    return { calque: ($('calque-choix') || { value: 'fond' }).value, type: type, points: [] };
  }

  function nommerLesModes() {
    var calque = enCours ? enCours.calque : (choixCalque || { value: '' }).value;
    var attendus = ((cfg.calques[calque] || {}).types) || [];
    Array.prototype.forEach.call(document.querySelectorAll('#outils-liste label[for^="outil-"]'), function (label) {
      var valeur = (label.getAttribute('for') || '').replace(/^outil-/, '');
      if (valeur === 'deplacer' || valeur === 'etiquette' || valeur === 'repositionner' || valeur === '') { label.classList.remove('mode--autre'); label.title = ''; return; }
      var type = valeur === 'libre' ? 'polygone' : valeur;
      var pertinent = attendus.indexOf(type) !== -1;
      label.classList.toggle('mode--autre', !pertinent);
      label.title = pertinent ? '' : "Ce calque attend plutôt : " + attendus.join(', ') + '.';
    });
  }

  function intensiteChoisie() {
    var sel = $('trace-intensite');
    return INTENSITES[sel && sel.value] || INTENSITES.dominant;
  }
  function motRepositionner() {
    var el = selectionEl();
    if (!el) { return 'Glissez une parcelle pour la déplacer ; son orientation se règle ensuite au curseur du panneau Données.'; }
    return orientable(el)
      ? 'Parcelle ' + el.libelle + ' — glissez-la pour la déplacer ; son orientation se règle au curseur du panneau Données.'
      : 'Parcelle ' + el.libelle + ' tracée à la main : elle se déplace, mais ne se tourne pas.';
  }
  function etat() {
    var n = enCours ? enCours.points.length : 0;
    var mot;
    if (rect) {
      mot = rect.centre ? 'Parcelle ' + rect.parcelle.code + ' posée — tournez-la, puis enregistrez.'
        : 'Cliquez sur la carte à l\'endroit du centre de la parcelle ' + rect.parcelle.code + '.';
    } else if (!enCours) {
      mot = mode === 'repositionner' ? motRepositionner() : { deplacer: 'Glissez pour déplacer, molette pour zoomer.',
              etiquette: 'Glissez une étiquette pour la déplacer ; double-clic pour la remettre à sa place.',
              point: 'Cliquez : un point.',
              ligne: 'Cliquez point après point ; double-clic ou Entrée pour finir, clic droit pour défaire.',
              polygone: 'Cliquez le contour ; double-clic ou Entrée pour fermer, clic droit pour défaire.',
              libre: 'Appuyez et suivez le contour à la main ; relâchez pour fermer.',
              rectangle: 'Cliquez le centre de la parcelle ; clic droit pour la reprendre.' }[mode];
    } else {
      var mesure = enCours.type === 'polygone' && n > 2 ? ' — ' + fmt(surface(enCours.points))
        : enCours.type === 'ligne' && n > 1 ? ' — ' + arrondi(longueur(enCours.points), 0) + ' m' : '';
      mot = n + ' point' + (n > 1 ? 's' : '') + mesure
        + '. Clic droit défait le dernier, Échap annule tout.';
    }
    texte('trace-etat', mot);
    var pret = (rect && rect.centre) || (enCours && enCours.points.length >= ({ point: 1, ligne: 2, polygone: 3 }[enCours.type] || 1));
    if ($('trace-enregistrer')) { $('trace-enregistrer').disabled = !pret; }
    var h = $('trace-hauteur-champ');
    if (h) { h.hidden = !(enCours && (enCours.calque === 'ombres' || enCours.calque === 'vegetation')); }
    var v = $('trace-intensite-champ');
    var surLesVents = (enCours ? enCours.calque : (choixCalque || {}).value) === 'vents';
    if (v) { v.hidden = !surLesVents; }
    // La rose ne se trace pas : elle s'affiche tant qu'on est sur le
    // calque des vents, tracé en cours ou non — au contraire du champ
    // d'intensité ci-dessus, qui ne sert qu'une fois le tracé commencé.
    var r = $('vents-rose-bloc');
    if (r) { r.hidden = (choixCalque || {}).value !== 'vents'; }
    var rf = $('relief-bloc');
    if (rf) { rf.hidden = (choixCalque || {}).value !== 'relief'; }
    var om = $('ombres-bloc');
    if (om) { om.hidden = (choixCalque || {}).value !== 'ombres'; }
  }
  function ajouterPoint(e) {
    enCours.points.push(latlon(e));
    etat(); tout();
  }
  function annuler() {
    enCours = null; rect = null; enLibre = false; selection = null; previsu = null;
    if ($('parcelle-angle')) { $('parcelle-angle').disabled = false; }
    if ($('parcelle-choix')) { $('parcelle-choix').value = ''; }
    etat(); tout();
  }
  function terminer() {
    if (!enCours) { return; }
    if (enCours.type === 'ligne' && enCours.points.length < 2) { return; }
    if (enCours.type === 'polygone' && enCours.points.length < 3) { return; }
    etat(); tout();
    if ($('trace-libelle')) { $('trace-libelle').focus(); }
  }

  svg.addEventListener('mousedown', function (e) {
    presse = { x: e.clientX, y: e.clientY };
    if (mode === 'libre') {
      e.stopPropagation(); e.preventDefault();
      enCours = nouveau('polygone'); enLibre = true;
      ajouterPoint(e);
    }
  });
  window.addEventListener('mousemove', function (e) {
    if (!enLibre || !enCours) { return; }
    var r = conteneur.getBoundingClientRect();
    var d = enCours.points.length ? px(enCours.points[enCours.points.length - 1]) : null;
    if (!d || Math.hypot(e.clientX - r.left - d[0], e.clientY - r.top - d[1]) > 7) { ajouterPoint(e); }
  });
  window.addEventListener('mouseup', function () {
    if (enLibre) { enLibre = false; terminer(); }
  });
  svg.addEventListener('click', function (e) {
    if (mode === 'deplacer' || mode === 'libre' || mode === 'etiquette' || mode === 'repositionner') { return; }
    if (presse && Math.hypot(e.clientX - presse.x, e.clientY - presse.y) > 4) { return; }
    if (mode === 'rectangle') {
      if (!rect) { return; }
      rect.centre = latlon(e); etat(); tout(); return;
    }
    if (mode === 'point') {
      enCours = nouveau('point'); ajouterPoint(e); terminer(); return;
    }
    if (!enCours) { enCours = nouveau(mode); }
    ajouterPoint(e);
  });
  /* LE CLIC DROIT DÉFAIT LE DERNIER POINT. Un tracé se rate d'un point,
   * pas en entier. Le menu du navigateur est écarté pendant qu'on dessine. */
  svg.addEventListener('contextmenu', function (e) {
    if (mode === 'deplacer' || mode === 'etiquette' || mode === 'repositionner') { return; }
    e.preventDefault();
    if (rect && rect.centre) { rect.centre = null; etat(); tout(); return; }
    if (!enCours || !enCours.points.length) { annuler(); return; }
    enCours.points.pop();
    if (!enCours.points.length) { enCours = null; }
    etat(); tout();
  });

  /* DÉPLACER UNE ÉTIQUETTE. On saisit le texte, il suit le pointeur (avec son
   * trait de rappel), et c'est au relâchement seulement qu'on enregistre : une
   * écriture par geste, pas une par pixel. Le clic sans mouvement ne fait rien. */
  var glisse = null;
  function objetDe(noeudTexte) {
    var id = noeudTexte && noeudTexte.getAttribute('data-id');
    var trouves = elements.filter(function (e) { return String(e.id) === String(id); });
    return trouves[0] || null;
  }
  function etiquetteSous(e) {
    if (mode !== 'etiquette' || !cfg.peutEcrire) { return null; }
    var t = e.target && e.target.closest ? e.target.closest('text.calque-etiquette--mobile') : null;
    return t && objetDe(t) ? t : null;
  }
  svg.addEventListener('pointerdown', function (e) {
    var t = etiquetteSous(e);
    if (!t) { return; }
    e.preventDefault(); e.stopPropagation();
    var el = objetDe(t);
    var ancre = t.getAttribute('data-ancre').split(',').map(Number);
    var guide = svg.querySelector('line.calque-guide[data-id="' + el.id + '"]');
    var guideCree = !guide;
    if (!guide) {
      guide = document.createElementNS(NS, 'line');
      guide.setAttribute('class', 'calque-guide');
      guide.setAttribute('stroke', t.getAttribute('fill'));
      guide.setAttribute('stroke-width', 1.2);
      guide.setAttribute('stroke-opacity', 0.7);
      guide.setAttribute('stroke-dasharray', '3 3');
      guide.style.display = 'none';
      svg.insertBefore(guide, t);
    }
    glisse = { el: el, texte: t, guide: guide, guideCree: guideCree, ancre: ancre, bouge: false,
      depart: [parseFloat(t.getAttribute('x')), parseFloat(t.getAttribute('y'))],
      souris: [e.clientX, e.clientY] };
    try { t.setPointerCapture(e.pointerId); } catch (x) { /* la capture est un confort */ }
    svg.classList.add('etiquette-saisie');
  });
  svg.addEventListener('pointermove', function (e) {
    if (!glisse) { return; }
    var dx = e.clientX - glisse.souris[0], dy = e.clientY - glisse.souris[1];
    if (!glisse.bouge && Math.hypot(dx, dy) < 3) { return; }
    glisse.bouge = true;
    var x = glisse.depart[0] + dx, y = glisse.depart[1] + dy;
    glisse.texte.setAttribute('x', x); glisse.texte.setAttribute('y', y);
    glisse.pos = [x, y];
    var loin = Math.hypot(x - glisse.ancre[0], y - glisse.ancre[1]) > ECART_GUIDE;
    glisse.guide.style.display = loin ? '' : 'none';
    glisse.guide.setAttribute('x1', glisse.ancre[0]); glisse.guide.setAttribute('y1', glisse.ancre[1]);
    glisse.guide.setAttribute('x2', x); glisse.guide.setAttribute('y2', y - 4);
  });
  function lacher(e, annule) {
    if (!glisse) { return; }
    var g = glisse; glisse = null;
    svg.classList.remove('etiquette-saisie');
    // Un clic sans mouvement ne redessine rien : le texte visé doit exister encore
    // au second clic d'un double-clic (qui remet l'étiquette en place).
    if (annule) { tout(); return; }
    if (!g.bouge) {
      if (g.guideCree && g.guide.parentNode) { g.guide.parentNode.removeChild(g.guide); }
      return;
    }
    var p = conteneur.carte.versLatLon(g.pos[0], g.pos[1]);
    poserEtiquette(g.el, [arrondi(p[0], 5), arrondi(p[1], 5)]);
  }
  svg.addEventListener('pointerup', function (e) { lacher(e, false); });
  svg.addEventListener('pointercancel', function (e) { lacher(e, true); });

  /* Pose l'étiquette de cet objet — ou la remet à sa place (`pos` nul). Les
   * autres propriétés (hauteur, angle, surface…) voyagent avec : le serveur
   * remplace `proprietes` en bloc. */
  function poserEtiquette(el, pos) {
    var props = {};
    Object.keys(el.proprietes || {}).forEach(function (k) { props[k] = el.proprietes[k]; });
    if (pos) { props.etiquette = pos; } else { delete props.etiquette; }
    envoyer({ action: 'modifier', id: el.id, proprietes: JSON.stringify(props) }).then(function (j) {
      if (!j.ok) { message(j.message); tout(); return; }
      el.proprietes = j.element.proprietes;
      tout();
      message(pos ? 'Étiquette déplacée.' : 'Étiquette remise à sa place.');
    }).catch(function () { message("Le serveur n'a pas répondu — l'étiquette reste où elle était."); tout(); });
  }

  /* REPOSITIONNER UNE PARCELLE. On saisit la parcelle, elle suit le pointeur
   * (le SVG entier se translate, sans rien redessiner : la capture du pointeur
   * tient), et c'est au relâchement qu'on enregistre — une écriture par geste,
   * comme pour les étiquettes. Le déplacement est arrondi à la grille des cinq
   * décimales, celle du serveur : tous les sommets bougent du même pas, la forme
   * reste exacte (au prix d'un pas d'environ un mètre). Ni les dimensions ni les
   * planches ne changent — elles s'éditent dans conception-parcelles.php. */
  function parcelleMobile(el) {
    return !!el && el.calque === 'parcelles' && el.type === 'polygone' && el.geometrie.length >= 3;
  }
  function orientable(el) {
    var q = el.proprietes || {};
    return typeof q.angle === 'number' && q.largeur_m > 0 && q.longueur_m > 0;
  }
  function centreDe(el) {
    var c = el.proprietes && el.proprietes.centre;
    return c && c.length === 2 && isFinite(c[0]) && isFinite(c[1]) ? c : centre(el.geometrie);
  }
  function selectionEl() {
    var trouves = selection ? elements.filter(function (e) { return e.id === selection.id; }) : [];
    return trouves[0] || null;
  }
  function copie(o) {
    var c = {};
    Object.keys(o || {}).forEach(function (k) { c[k] = o[k]; });
    return c;
  }
  /* Choisir une parcelle : elle se met en valeur, et le curseur d'orientation prend
   * la sienne (grisé si elle a été tracée à la main : sa forme est libre). */
  function choisir(el) {
    selection = { id: el.id };
    previsu = null;
    var a = $('parcelle-angle');
    if (a) {
      a.disabled = !orientable(el);
      if (orientable(el)) { a.value = el.proprietes.angle; texte('parcelle-angle-valeur', el.proprietes.angle + '°'); }
    }
    etat();
  }
  function ecrireParcelle(el, points, props, dit) {
    envoyer({ action: 'modifier', id: el.id, geometrie: JSON.stringify(points), proprietes: JSON.stringify(props) })
      .then(function (j) {
        previsu = null;
        if (!j.ok) { message(j.message); tout(); return; }
        el.geometrie = j.element.geometrie; el.proprietes = j.element.proprietes;
        tout(); listes(); etat(); message(dit);
      }).catch(function () {
        previsu = null; tout(); message("Le serveur n'a pas répondu — la parcelle reste où elle était.");
      });
  }
  function deplacerParcelle(el, dLat, dLon) {
    var points = el.geometrie.map(function (q) { return [arrondi(q[0] + dLat, 5), arrondi(q[1] + dLon, 5)]; });
    var props = copie(el.proprietes);
    if (orientable(el)) {
      var c = centreDe(el);
      props.centre = [arrondi(c[0] + dLat, 6), arrondi(c[1] + dLon, 6)];
    }
    var e = props.etiquette;
    if (e && e.length === 2 && isFinite(e[0]) && isFinite(e[1])) {
      props.etiquette = [arrondi(e[0] + dLat, 5), arrondi(e[1] + dLon, 5)];
    }
    ecrireParcelle(el, points, props, 'Parcelle ' + el.libelle + ' déplacée.');
  }

  var parc = null;
  function parcelleSous(e) {
    if (mode !== 'repositionner' || !cfg.peutEcrire) { return null; }
    var n = e.target && e.target.closest ? e.target.closest('polygon.parcelle-mobile') : null;
    var el = n ? objetDe(n.parentNode) : null;
    return parcelleMobile(el) ? { noeud: n, el: el } : null;
  }
  /* Le geste s'écoute sur la fenêtre, pas sur la parcelle : une parcelle de huit
   * mètres ne fait que vingt points d'écran, le pointeur la quitte au premier
   * mouvement rapide, et la capture du pointeur ne suffit pas à le suivre
   * (vérifié : des pas de trente points perdaient le geste). */
  function bougerParcelle(e) {
    if (!parc) { return; }
    var dx = e.clientX - parc.souris[0], dy = e.clientY - parc.souris[1];
    if (!parc.bouge && Math.hypot(dx, dy) < 3) { return; }
    parc.bouge = true; parc.dx = dx; parc.dy = dy;
    Array.prototype.forEach.call(svg.querySelectorAll('[data-id="' + parc.el.id + '"]'), function (n) {
      n.setAttribute('transform', 'translate(' + dx + ' ' + dy + ')');
    });
  }
  function finirParcelle() { lacherParcelle(false); }
  function annulerParcelle() { lacherParcelle(true); }
  function lacherParcelle(annule) {
    if (!parc) { return; }
    var p = parc; parc = null;
    window.removeEventListener('pointermove', bougerParcelle);
    window.removeEventListener('pointerup', finirParcelle);
    window.removeEventListener('pointercancel', annulerParcelle);
    svg.classList.remove('parcelle-saisie');
    if (annule || !p.bouge) { tout(); return; }
    var r = conteneur.getBoundingClientRect();
    var x0 = p.souris[0] - r.left, y0 = p.souris[1] - r.top;
    var a = conteneur.carte.versLatLon(x0, y0), b = conteneur.carte.versLatLon(x0 + p.dx, y0 + p.dy);
    var pas = function (d) { return Math.round(d * 1e5) / 1e5; };
    var dLat = pas(b[0] - a[0]), dLon = pas(b[1] - a[1]);
    if (dLat === 0 && dLon === 0) { tout(); return; }
    deplacerParcelle(p.el, dLat, dLon);
  }
  svg.addEventListener('pointerdown', function (e) {
    var s = parcelleSous(e);
    if (!s) { return; }
    e.preventDefault(); e.stopPropagation();
    // Pas de redessin ici : le nœud saisi doit exister jusqu'au relâchement.
    choisir(s.el);
    parc = { el: s.el, souris: [e.clientX, e.clientY], bouge: false, dx: 0, dy: 0 };
    window.addEventListener('pointermove', bougerParcelle);
    window.addEventListener('pointerup', finirParcelle);
    window.addEventListener('pointercancel', annulerParcelle);
    svg.classList.add('parcelle-saisie');
  });

  svg.addEventListener('dblclick', function (e) {
    var t = etiquetteSous(e);
    if (t) {
      e.preventDefault();
      var el = objetDe(t);
      if (el.proprietes && el.proprietes.etiquette) { poserEtiquette(el, null); }
      return;
    }
    e.preventDefault();
    if (enCours && (mode === 'ligne' || mode === 'polygone')) { terminer(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) { return; }
    if (e.key === 'Escape') { annuler(); }
    if (e.key === 'Enter' && enCours) { terminer(); }
  });

  // ---------------------------------------------------------------- panneau
  function changerMode(m) {
    mode = m;
    var bouton = document.getElementById('outil-' + m);
    if (bouton && !bouton.checked) {
      bouton.checked = true;
      bouton.dispatchEvent(new Event('change', { bubbles: true }));
    }
    // Déplacer et Étiquettes laissent la carte se faire glisser : seules les
    // étiquettes, en mode Étiquettes, reprennent la souris (voir le CSS de la page).
    var carteLibre = mode === 'deplacer' || mode === 'etiquette' || mode === 'repositionner';
    svg.style.pointerEvents = carteLibre ? 'none' : 'auto';
    svg.style.cursor = carteLibre ? '' : 'crosshair';
    svg.classList.toggle('mode-etiquette', mode === 'etiquette');
    svg.classList.toggle('mode-parcelle', mode === 'repositionner');
    if (mode !== 'rectangle') { rect = null; }
    selection = null; previsu = null;
    if ($('parcelle-angle')) { $('parcelle-angle').disabled = false; }
    enCours = null; enLibre = false;
    nommerLesModes();
    etat(); tout();
  }
  if (window.Gabarit && window.Gabarit.onOutilChange) {
    window.Gabarit.onOutilChange(function (id) {
      changerMode(String(id).replace(/^outil-/, ''));
    });
  }
  /* L'EAU DÉJÀ CARTOGRAPHIÉE — un clic, un appel, sur l'emprise du fond. */
  var boutonEau = $('eau-chercher');
  if (boutonEau && cfg.peutEcrire && cfg.apiEau) {
    var blocEau = document.querySelector('.chercher-eau');
    if (blocEau) { blocEau.hidden = false; }
    boutonEau.addEventListener('click', function () {
      boutonEau.disabled = true;
      texte('eau-etat', 'Je regarde ce qu\'OpenStreetMap connaît autour du terrain…');
      fetch(cfg.apiEau, { method: 'POST', body: new URLSearchParams({
        csrf: cfg.csrf, site_id: cfg.siteId }) })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          boutonEau.disabled = false;
          texte('eau-etat', j.message || '');
          if (j.ok && j.elements && j.elements.length) {
            elements = elements.concat(j.elements);
            if (boutonCentrer) { boutonCentrer.hidden = false; }
            listes(); tout();
          }
        })
        .catch(function () {
          boutonEau.disabled = false;
          texte('eau-etat', "Le service n'a pas répondu. L'eau se trace à la main.");
        });
    });
  }
  /* LE RELIEF — une grille d'altitudes IGN sur l'emprise du fond, comme
   * l'eau. Un seul relevé par site : le nouveau remplace l'ancien, ici
   * comme au serveur — sinon l'ancienne grille resterait visible jusqu'au
   * rechargement. */
  var boutonAltitude = $('altitude-relever');
  if (boutonAltitude && cfg.peutEcrire && cfg.apiAltitude) {
    boutonAltitude.addEventListener('click', function () {
      boutonAltitude.disabled = true;
      texte('altitude-etat', "Je relève les altitudes IGN sur l'emprise du terrain…");
      fetch(cfg.apiAltitude, { method: 'POST', body: new URLSearchParams({
        csrf: cfg.csrf, site_id: cfg.siteId }) })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          boutonAltitude.disabled = false;
          texte('altitude-etat', j.message || '');
          if (j.ok && j.element) {
            elements = elements.filter(function (e) {
              return !(e.calque === 'relief' && e.type === 'grille');
            }).concat([j.element]);
            if (boutonCentrer) { boutonCentrer.hidden = false; }
            listes(); tout();
          }
        })
        .catch(function () {
          boutonAltitude.disabled = false;
          texte('altitude-etat', "Le service n'a pas répondu. Réessayez dans un moment.");
        });
    });
  }

  var caseCadastre = $('cadastre-visible');
  var blocCadastre = document.querySelector('.cadastre-bascule');
  if (caseCadastre && cfg.apiCadastre) {
    if (blocCadastre) { blocCadastre.hidden = false; }
    cadastre.visible = caseCadastre.checked;
    caseCadastre.addEventListener('change', function () {
      cadastre.visible = caseCadastre.checked;
      if (cadastre.visible) { chargerCadastre(); } else { etatCadastre(); tout(); }
    });
  }

  /* L'IMPORT SUIT LE CALQUE. Le formulaire d'import est en bas du panneau, loin de
   * ce qu'on regarde : « sur le calque » reprend donc celui qu'on a choisi, et les
   * liens « Importer un fichier » des blocs relief et ombres règlent le leur. */
  var calqueImport = $('import-calque');
  function suivreCalque() {
    if (calqueImport && choixCalque && cfg.calques[choixCalque.value]) { calqueImport.value = choixCalque.value; }
  }
  Array.prototype.forEach.call(document.querySelectorAll('a.va-import'), function (a) {
    a.addEventListener('click', function () {
      if (calqueImport && a.dataset.calque) { calqueImport.value = a.dataset.calque; }
    });
  });

  var choixCalque = $('calque-choix');
  if (choixCalque) {
    choixCalque.addEventListener('change', function () {
      suivreCalque();
      texte('calque-aide', (cfg.calques[choixCalque.value] || {}).aide || '');
      if (enCours && enCours.points.length) {
        enCours.calque = choixCalque.value;
      } else {
        changerMode('deplacer');
      }
      nommerLesModes();
      etat(); tout();
      // Le panneau est long : un calque qui a son bloc d'aide (vents, relief, ombres) le
      // montre juste sous ce choix. Si on avait défilé plus bas, on remonte à lui —
      // sans quoi le bloc s'affiche, hors de vue, et semble absent.
      var blocs = { vents: 'vents-rose-bloc', relief: 'relief-bloc', ombres: 'ombres-bloc' };
      var bloc = blocs[choixCalque.value] && $(blocs[choixCalque.value]);
      if (bloc && !bloc.hidden) { choixCalque.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
    });
    texte('calque-aide', (cfg.calques[choixCalque.value] || {}).aide || '');
  }
  var choixParcelle = $('parcelle-choix');
  if (choixParcelle) {
    choixParcelle.addEventListener('change', function () {
      var o = choixParcelle.selectedOptions[0];
      if (!o || !o.value) { rect = null; etat(); tout(); return; }
      var p = cfg.parcelles.filter(function (q) { return String(q.id) === o.value; })[0];
      if (!p) { return; }
      // Déjà posée : la reposer en ferait une seconde. On la reprend plutôt.
      var deja = elements.filter(function (e) { return e.calque === 'parcelles' && String(e.parcelle_id) === o.value; })[0];
      if (deja && parcelleMobile(deja)) {
        choixParcelle.value = '';
        changerMode('repositionner');
        choisir(deja); tout();
        message('La parcelle ' + p.code + ' est déjà posée : glissez-la pour la déplacer.');
        return;
      }
      if (p.largeur_m && p.longueur_m) {
        changerMode('rectangle');
        rect = { parcelle: p, centre: null, angle: parseFloat(($('parcelle-angle') || { value: 0 }).value) || 0,
                 largeur: p.largeur_m, longueur: p.longueur_m };
        etat(); tout();
      } else {
        rect = null;
        if (choixCalque) { choixCalque.value = 'parcelles'; choixCalque.dispatchEvent(new Event('change')); }
        changerMode('polygone');
        texte('trace-etat', 'La parcelle ' + p.code + ' n\'a pas de dimensions : tracez son contour à la main, puis enregistrez.');
      }
    });
  }
  var angle = $('parcelle-angle');
  if (angle) {
    angle.addEventListener('input', function () {
      if (rect) { rect.angle = parseFloat(angle.value) || 0; texte('parcelle-angle-valeur', angle.value + '°'); tout(); return; }
      // Une parcelle déjà posée se tourne autour de son centre : on montre le
      // résultat pendant qu'on glisse, on n'écrit qu'au relâchement (« change »).
      var el = mode === 'repositionner' ? selectionEl() : null;
      if (!el || !orientable(el)) { return; }
      var a = parseFloat(angle.value) || 0;
      texte('parcelle-angle-valeur', angle.value + '°');
      previsu = { id: el.id, angle: a, points: coins({ centre: centreDe(el), angle: a,
        largeur: el.proprietes.largeur_m, longueur: el.proprietes.longueur_m }) };
      tout();
    });
    angle.addEventListener('change', function () {
      var el = previsu ? selectionEl() : null;
      if (!el) { return; }
      var props = copie(el.proprietes), c = centreDe(el);
      props.angle = previsu.angle; props.centre = [arrondi(c[0], 6), arrondi(c[1], 6)];
      ecrireParcelle(el, previsu.points, props, 'Parcelle ' + el.libelle + ' tournée.');
    });
  }
  if ($('trace-enregistrer')) { $('trace-enregistrer').addEventListener('click', enregistrer); }
  if ($('trace-annuler')) { $('trace-annuler').addEventListener('click', annuler); }
  var boutonCentrer = $('carte-centrer');
  if (boutonCentrer) {
    boutonCentrer.addEventListener('click', function () {
      var sud = 90, nord = -90, ouest = 180, est = -180, vus = 0;
      elements.forEach(function (e) {
        (e.geometrie || []).forEach(function (p) {
          sud = Math.min(sud, p[0]); nord = Math.max(nord, p[0]);
          ouest = Math.min(ouest, p[1]); est = Math.max(est, p[1]);
          vus++;
        });
      });
      if (!vus) { return; }
      var large = Math.max((nord - sud) * 111320, (est - ouest) * 111320
        * Math.cos((nord + sud) / 2 * Math.PI / 180));
      var z = ZOOM_TERRAIN;
      while (z > 10 && large > conteneur.clientWidth * 156543.03392
        * Math.cos((nord + sud) / 2 * Math.PI / 180) / Math.pow(2, z)) { z--; }
      conteneur.carte.centrer((sud + nord) / 2, (ouest + est) / 2, z);
    });
  }

  if (boutonCentrer && elements.length) { boutonCentrer.hidden = false; }

  conteneur.addEventListener('carte:vue', tout);
  conteneur.addEventListener('carte:vue', cadastreBientot);
  window.addEventListener('resize', tout);
  changerMode('deplacer');
  nommerLesModes();
  listes();

  /* Le cadrage d'arrivée : le point du site d'abord — cadrer sur la
   * moyenne des tracés emporterait le centre avec un import lointain. */
  var cadre = false;
  function cadrer() {
    if (cadre || !conteneur.carte) { return; }
    cadre = true;
    var ou = cfg.centre;
    if (!ou) {
      var vus = [];
      elements.forEach(function (e) { vus = vus.concat(e.geometrie); });
      ou = vus.length ? centre(vus) : null;
    }
    if (ou) { conteneur.carte.centrer(ou[0], ou[1], ZOOM_TERRAIN); }
    tout();
    if (cadastre.visible) { chargerCadastre(); }
  }
  conteneur.addEventListener('carte:vue', cadrer);
  cadrer();
})();
