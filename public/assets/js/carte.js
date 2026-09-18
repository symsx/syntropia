/* Une carte, sans bibliothèque.
 *
 * Le projet n'a aucune dépendance, et ce n'est pas une posture : rien n'est
 * installé, tout se déploie par copie. Embarquer une bibliothèque de cartes
 * ferait 150 Ko de code qu'on ne maintient pas, pour un besoin qui tient en
 * deux cents lignes — afficher des tuiles, les déplacer, poser un point.
 *
 * Les tuiles viennent d'un tiers — OpenStreetMap par défaut, et, sur la grande
 * carte du terrain, les fonds de l'IGN (plan, photographies aériennes, photos
 * anciennes, infrarouge) : elles sont chargées par le navigateur du visiteur.
 * La carte est donc un CONFORT, jamais un passage obligé — latitude et
 * longitude restent saisissables à la main, et le formulaire fonctionne sans
 * ce fichier.
 *
 * Porté de application/public/assets/carte.js (dépôt de référence),
 * logique inchangée — voir plan-du-site.md et architecture-technique.md,
 * chantier « carte interactive du terrain », Phase 1.
 */
(function () {
  'use strict';

  var TUILE = 256;
  var ZOOM_MIN = 3;

  /* LES FONDS DE CARTE. Tous en Web Mercator, tuiles de 256 px : le moteur ne
   * change pas, seuls l'adresse, le crédit et le zoom le plus fin changent.
   * L'IGN sert ses couches par la Géoplateforme (WMTS, sans clé) : un fichier
   * de tuiles par (zoom, colonne, rangée). **Chaque zoom maximal vient du
   * catalogue de la Géoplateforme** (GetCapabilities), non d'une supposition —
   * les photographies anciennes s'arrêtent au 18, le reste va au 19.
   * `partiel` : une couverture qui ne couvre pas tout le territoire (les
   * photos d'avant 1995) — une tuile absente n'y est pas une panne. */
  function ign(couche, format, zoomMax, libelle, partiel) {
    return {
      libelle: libelle, zoomMax: zoomMax, partiel: !!partiel,
      credit: '© IGN', lien: 'https://geoservices.ign.fr/',
      url: function (z, x, y) {
        return 'https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=' + couche
          + '&STYLE=normal&FORMAT=' + format + '&TILEMATRIXSET=PM&TILEMATRIX=' + z
          + '&TILEROW=' + y + '&TILECOL=' + x;
      }
    };
  }
  var FONDS = {
    osm: {
      libelle: 'Carte OpenStreetMap', zoomMax: 18, partiel: false,
      credit: '© OpenStreetMap', lien: 'https://www.openstreetmap.org/copyright',
      url: function (z, x, y) { return 'https://tile.openstreetmap.org/' + z + '/' + x + '/' + y + '.png'; }
    },
    plan: ign('GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2', 'image/png', 19, 'Plan IGN'),
    photo: ign('ORTHOIMAGERY.ORTHOPHOTOS', 'image/jpeg', 19, 'Photographie aérienne'),
    irc: ign('ORTHOIMAGERY.ORTHOPHOTOS.IRC', 'image/jpeg', 19, 'Infrarouge (végétation)'),
    p1980: ign('ORTHOIMAGERY.ORTHOPHOTOS.1980-1995', 'image/png', 18, 'Photo ancienne 1980-1995', true),
    p1965: ign('ORTHOIMAGERY.ORTHOPHOTOS.1965-1980', 'image/png', 18, 'Photo ancienne 1965-1980', true),
    p1950: ign('ORTHOIMAGERY.ORTHOPHOTOS.1950-1965', 'image/png', 18, 'Photo ancienne 1950-1965', true)
  };
  var CLE_FOND = 'syntropia_fond';

  // Web Mercator. Le monde entier fait 256 × 2^z pixels de côté.
  function lonEnX(lon, z) { return (lon + 180) / 360 * TUILE * Math.pow(2, z); }
  function latEnY(lat, z) {
    var r = lat * Math.PI / 180;
    return (1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2
           * TUILE * Math.pow(2, z);
  }
  function xEnLon(x, z) { return x / (TUILE * Math.pow(2, z)) * 360 - 180; }
  function yEnLat(y, z) {
    var n = Math.PI - 2 * Math.PI * y / (TUILE * Math.pow(2, z));
    return 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
  }

  function creer(conteneur) {
    var champLat = document.getElementById(conteneur.dataset.latitude);
    var champLon = document.getElementById(conteneur.dataset.longitude);
    if (!champLat || !champLon) { return; }

    var lat = parseFloat(champLat.value);
    var lon = parseFloat(champLon.value);
    var pose = !isNaN(lat) && !isNaN(lon);
    // Sans point posé : la France, assez large pour se repérer.
    if (!pose) { lat = 46.6; lon = 2.4; }
    var z = pose ? 15 : 5;
    var cx = lonEnX(lon, z), cy = latEnY(lat, z);

    var couche = document.createElement('div');
    couche.className = 'carte-tuiles';
    var marqueur = document.createElement('div');
    marqueur.className = 'carte-point';
    marqueur.hidden = !pose;
    // Le fond : OpenStreetMap, sauf sur une carte qui offre le choix
    // (`data-fond-choix` : l'identifiant d'un <select>) — où la préférence du
    // visiteur, gardée dans son navigateur, l'emporte. Sans stockage
    // (navigation privée…) la carte reste sur OpenStreetMap, sans erreur.
    var choixFond = conteneur.dataset.fondChoix ? document.getElementById(conteneur.dataset.fondChoix) : null;
    var idFond = 'osm';
    if (choixFond) {
      try { var garde = window.localStorage.getItem(CLE_FOND); if (garde && FONDS[garde]) { idFond = garde; } } catch (x) { /* confort */ }
    }
    var fond = FONDS[idFond];
    var credit = document.createElement('a');
    credit.className = 'carte-credit';
    credit.href = fond.lien;
    credit.rel = 'noreferrer';
    credit.textContent = fond.credit;
    conteneur.append(couche, marqueur, credit);
    conteneur.tabIndex = 0;

    var tuiles = {};
    var echecs = 0;

    /* Une carte muette est pire qu'une carte absente : on croit que le
     * point n'a pas été posé. Si les tuiles ne viennent pas — hors ligne,
     * blocage, service indisponible — la carte le dit et rappelle que la
     * saisie manuelle, elle, fonctionne toujours. */
    function signalerEchec() {
      if (++echecs < 3 || conteneur.querySelector('.carte-panne')) { return; }
      var mot = document.createElement('p');
      mot.className = 'carte-panne';
      // Une photo ancienne ne couvre pas tout : des tuiles absentes n'y sont pas une panne.
      mot.textContent = fond.partiel
        ? "Cette photographie ancienne ne couvre pas cet endroit — essayez une autre période, ou la photographie récente."
        : 'Les tuiles ne se chargent pas. Saisissez les coordonnées à la main : tout le reste fonctionne.';
      conteneur.appendChild(mot);
    }

    function dessiner() {
      var l = conteneur.clientWidth, h = conteneur.clientHeight;
      var n = Math.pow(2, z);
      var x0 = Math.floor((cx - l / 2) / TUILE), x1 = Math.floor((cx + l / 2) / TUILE);
      var y0 = Math.floor((cy - h / 2) / TUILE), y1 = Math.floor((cy + h / 2) / TUILE);
      var vues = {};

      for (var tx = x0; tx <= x1; tx++) {
        for (var ty = y0; ty <= y1; ty++) {
          if (ty < 0 || ty >= n) { continue; }          // pas de tuile aux pôles
          var mx = ((tx % n) + n) % n;                   // le monde s'enroule
          var cle = z + '/' + mx + '/' + ty;
          vues[cle] = true;
          var img = tuiles[cle];
          if (!img) {
            img = document.createElement('img');
            // OpenStreetMap EXIGE un Referer — sans lui, 403 « referer is
            // required by tile usage policy ». Or les pages posent
            // `Referrer-Policy: same-origin`, qui n'en envoie aucun aux
            // tiers. L'attribut de l'élément l'emporte sur celui du
            // document : `origin` n'envoie que le nom du site, jamais la
            // page consultée. La politique de la page reste donc stricte
            // partout ailleurs.
            img.referrerPolicy = 'origin';
            img.src = fond.url(z, mx, ty);
            img.alt = '';
            img.loading = 'lazy';
            img.draggable = false;
            img.addEventListener('error', signalerEchec);
            couche.appendChild(img);
            tuiles[cle] = img;
          }
          img.style.left = (tx * TUILE - cx + l / 2) + 'px';
          img.style.top = (ty * TUILE - cy + h / 2) + 'px';
        }
      }
      // Ne pas garder les tuiles sorties du cadre : sans cela, une longue
      // exploration finit par empiler des milliers d'images.
      for (var cle2 in tuiles) {
        if (!vues[cle2]) { couche.removeChild(tuiles[cle2]); delete tuiles[cle2]; }
      }
      placerMarqueur();
      // La vue a changé : ce qui est dessiné par-dessus doit suivre. Voir
      // `calques.js`, qui écoute cet événement et redessine ses tracés.
      conteneur.dispatchEvent(new CustomEvent('carte:vue', { bubbles: true }));
    }

    function placerMarqueur() {
      var la = parseFloat(champLat.value), lo = parseFloat(champLon.value);
      if (isNaN(la) || isNaN(lo)) { marqueur.hidden = true; return; }
      marqueur.hidden = false;
      marqueur.style.left = (lonEnX(lo, z) - cx + conteneur.clientWidth / 2) + 'px';
      marqueur.style.top = (latEnY(la, z) - cy + conteneur.clientHeight / 2) + 'px';
    }

    function poser(px, py) {
      var l = conteneur.clientWidth, h = conteneur.clientHeight;
      var la = yEnLat(cy + py - h / 2, z), lo = xEnLon(cx + px - l / 2, z);
      // Cinq décimales : environ un mètre. Au-delà, on affiche une précision
      // que la carte n'a pas.
      champLat.value = la.toFixed(5);
      champLon.value = lo.toFixed(5);
      placerMarqueur();
      // Un événement à nous, et non `change` sur le champ : `change`
      // signifie « quelqu'un a tapé une coordonnée », et provoque un
      // recentrage. Poser un point sur la carte ne doit pas la faire
      // sauter sous le curseur — on vient justement de viser.
      conteneur.dispatchEvent(new CustomEvent('carte:point', {
        bubbles: true, detail: { latitude: champLat.value, longitude: champLon.value }
      }));
    }

    function zoomer(delta, px, py) {
      var nouveau = Math.max(ZOOM_MIN, Math.min(fond.zoomMax, z + delta));
      if (nouveau === z) { return; }
      var l = conteneur.clientWidth, h = conteneur.clientHeight;
      // Garder sous le curseur le point qui s'y trouvait.
      var la = yEnLat(cy + py - h / 2, z), lo = xEnLon(cx + px - l / 2, z);
      z = nouveau;
      cx = lonEnX(lo, z) - (px - l / 2);
      cy = latEnY(la, z) - (py - h / 2);
      Object.keys(tuiles).forEach(function (c) { couche.removeChild(tuiles[c]); });
      tuiles = {};
      dessiner();
    }

    // --- déplacement -------------------------------------------------
    var glisse = null, bouge = false;
    function debut(e) {
      // Les réglages posés sur la carte (échelle, fond, cadrage) ne la font pas glisser.
      if (e.target && e.target.closest && e.target.closest('.carte-reglures')) { return; }
      var p = e.touches ? e.touches[0] : e;
      glisse = { x: p.clientX, y: p.clientY };
      bouge = false;
    }
    function suite(e) {
      if (!glisse) { return; }
      var p = e.touches ? e.touches[0] : e;
      var dx = p.clientX - glisse.x, dy = p.clientY - glisse.y;
      if (Math.abs(dx) + Math.abs(dy) > 3) { bouge = true; }
      cx -= dx; cy -= dy;
      glisse = { x: p.clientX, y: p.clientY };
      if (e.cancelable) { e.preventDefault(); }
      dessiner();
    }
    function fin() { glisse = null; }

    conteneur.addEventListener('mousedown', debut);
    window.addEventListener('mousemove', suite);
    window.addEventListener('mouseup', fin);
    conteneur.addEventListener('touchstart', debut, { passive: true });
    conteneur.addEventListener('touchmove', suite, { passive: false });
    conteneur.addEventListener('touchend', fin);

    conteneur.addEventListener('click', function (e) {
      // Un glissement se termine par un clic : ne pas déplacer le point
      // parce qu'on a déplacé la carte.
      // `data-fixe` : la carte sert à dessiner, non à poser le point du
      // site — un clic n'y déplace rien. Voir conception-etude-site.php.
      if (bouge || e.target === credit || conteneur.dataset.fixe !== undefined) { return; }
      var r = conteneur.getBoundingClientRect();
      poser(e.clientX - r.left, e.clientY - r.top);
    });

    conteneur.addEventListener('wheel', function (e) {
      if (e.target && e.target.closest && e.target.closest('.carte-reglures')) { return; }
      e.preventDefault();
      var r = conteneur.getBoundingClientRect();
      zoomer(e.deltaY < 0 ? 1 : -1, e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });

    // Au clavier : la carte doit rester utilisable sans souris.
    conteneur.addEventListener('keydown', function (e) {
      var pas = 60, traite = true;
      if (e.key === 'ArrowLeft') { cx -= pas; }
      else if (e.key === 'ArrowRight') { cx += pas; }
      else if (e.key === 'ArrowUp') { cy -= pas; }
      else if (e.key === 'ArrowDown') { cy += pas; }
      else if (e.key === '+' || e.key === '=') {
        zoomer(1, conteneur.clientWidth / 2, conteneur.clientHeight / 2); return;
      } else if (e.key === '-') {
        zoomer(-1, conteneur.clientWidth / 2, conteneur.clientHeight / 2); return;
      } else if (e.key === 'Enter') {
        if (conteneur.dataset.fixe === undefined) { poser(conteneur.clientWidth / 2, conteneur.clientHeight / 2); }
        return;
      } else { traite = false; }
      if (traite) { e.preventDefault(); dessiner(); }
    });

    // La saisie manuelle reste maîtresse : taper une latitude recentre.
    [champLat, champLon].forEach(function (champ) {
      champ.addEventListener('change', function () {
        var la = parseFloat(champLat.value), lo = parseFloat(champLon.value);
        if (isNaN(la) || isNaN(lo)) { placerMarqueur(); return; }
        if (z < 12) { z = 15; tuiles = {}; couche.textContent = ''; }
        cx = lonEnX(lo, z); cy = latEnY(la, z);
        dessiner();
      });
    });

    /* CE QUE LA CARTE EXPOSE À CE QUI SE DESSINE PAR-DESSUS. La projection
     * reste privée ; ces quatre fonctions suffisent à `calques.js` pour
     * convertir dans les deux sens et tracer une échelle. */
    conteneur.carte = {
      versPixel: function (la, lo) {
        return [lonEnX(lo, z) - cx + conteneur.clientWidth / 2,
                latEnY(la, z) - cy + conteneur.clientHeight / 2];
      },
      versLatLon: function (px, py) {
        return [yEnLat(cy + py - conteneur.clientHeight / 2, z),
                xEnLon(cx + px - conteneur.clientWidth / 2, z)];
      },
      zoom: function () { return z; },
      // Web Mercator : 156 543 m par pixel à l'équateur au zoom 0.
      metresParPixel: function () {
        var la = yEnLat(cy, z) * Math.PI / 180;
        return 156543.03392 * Math.cos(la) / Math.pow(2, z);
      },
      centrer: function (la, lo, zoom) {
        if (typeof zoom === 'number') { zoom = Math.min(zoom, fond.zoomMax); }
        if (typeof zoom === 'number' && zoom !== z) { z = zoom; tuiles = {}; couche.textContent = ''; }
        cx = lonEnX(lo, z); cy = latEnY(la, z); dessiner();
      },
      fond: function () { return idFond; },
      changerFond: changerFond
    };

    /* CHANGER DE FOND. Les tuiles déjà là sont celles de l'ancien fond : on les
     * retire toutes. Si l'on était plus près que le nouveau fond ne le permet
     * (une photo ancienne s'arrête au zoom 18), on recule au plus fin qu'il
     * sert, sans bouger le centre. Ce qui est dessiné par-dessus suit. */
    function changerFond(id) {
      if (!FONDS[id] || id === idFond) { return; }
      idFond = id; fond = FONDS[id];
      credit.href = fond.lien; credit.textContent = fond.credit;
      if (z > fond.zoomMax) {
        var la = yEnLat(cy, z), lo = xEnLon(cx, z);
        z = fond.zoomMax; cx = lonEnX(lo, z); cy = latEnY(la, z);
      }
      Object.keys(tuiles).forEach(function (c) { couche.removeChild(tuiles[c]); });
      tuiles = {}; echecs = 0;
      var panne = conteneur.querySelector('.carte-panne');
      if (panne) { conteneur.removeChild(panne); }
      try { window.localStorage.setItem(CLE_FOND, id); } catch (x) { /* confort */ }
      dessiner();
      conteneur.dispatchEvent(new CustomEvent('carte:fond', { bubbles: true, detail: { fond: id } }));
    }
    if (choixFond) {
      Object.keys(FONDS).forEach(function (id) {
        var o = document.createElement('option');
        o.value = id; o.textContent = FONDS[id].libelle;
        choixFond.appendChild(o);
      });
      choixFond.value = idFond;
      choixFond.addEventListener('change', function () { changerFond(choixFond.value); });
    }

    window.addEventListener('resize', dessiner);
    dessiner();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-carte]').forEach(creer);
  });
})();
