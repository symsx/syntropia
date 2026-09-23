/* Étape « Créer les parcelles » — propre à cette page, chargé après
   gabarit.js. Le gabarit (thème, tiroirs, Échap, menu d'outils, bulle)
   est déjà en place ; ce script ne s'occupe que de la géométrie. */
(function(){
  'use strict';

  var bureau = document.getElementById('bureau');
  var svg = document.getElementById('plan');
  var champLongueur = document.getElementById('in-longueur');
  var champLargeur = document.getElementById('in-largeur');
  // Chargées de PLAN_CONSTANTES (conception-parcelles.php, depuis
  // Parcelles::PLANCHE_M/PASSE_PIED_M) plutôt que recopiées en dur ici :
  // le calcul serveur de nb_planches doit voir les mêmes nombres que
  // l'aperçu tracé à l'écran, jamais deux sources qui pourraient diverger.
  var cfgPlan = (typeof PLAN_CONSTANTES !== 'undefined') ? PLAN_CONSTANTES : {};
  var PLANCHE = cfgPlan.planche || 1.2, PASSE_PIED = cfgPlan.passePied || 0.5;
  var BORD_PLANCHE = cfgPlan.bordPlanche || 0.6;

  function nombre(valeur){ return parseFloat(String(valeur).replace(',', '.')) || 0; }
  function fmt(valeur, decimales){ return valeur.toFixed(decimales === undefined ? 2 : decimales).replace('.', ','); }
  function champDe(cote){ return document.querySelector('.bordure-largeur[data-cote="' + cote + '"]'); }

  function bordures(){
    var b = {};
    document.querySelectorAll('.bordure-active').forEach(function(c){
      var cote = c.dataset.cote;
      b[cote] = c.checked ? nombre(champDe(cote).value) : 0;
    });
    return b;
  }

  /* La bordure interne (biomasse) — Phase 4 : même lecture que l'externe,
     champs distincts. */
  function bordureInterne(){
    var b = {};
    document.querySelectorAll('.bordure-interne-active').forEach(function(c){
      var cote = c.dataset.cote;
      b[cote] = c.checked ? nombre(champDeInterne(cote).value) : 0;
    });
    return b;
  }

  /* La largeur de chaque planche d'un rang, et de chaque passe-pied entre
   * elles — en mètres. Chantier « plan de parcelle », Phase 3
   * (2026-09-18) : les deux planches de bord (la première et la
   * dernière, celles qui touchent une bordure plutôt qu'un passe-pied)
   * font toujours BORD_PLANCHE, jamais PLANCHE plein pot — un seul côté
   * accessible, moitié moins de portée. Le reste entre le compte idéal et
   * la largeur disponible se répartit sur les planches intérieures ou sur
   * les passe-pieds, au choix (`rattrapage`), jamais les deux. Même
   * formule que `Parcelles::enregistrerPlan()` côté serveur (le nombre de
   * planches, pas leur largeur individuelle — un calcul purement client,
   * voir chantier-plan-de-parcelle.md § Phase 3). */
  function planchesLayout(disponible, rattrapage){
    var pas = PLANCHE + PASSE_PIED;
    var n = disponible > PLANCHE ? Math.max(1, Math.floor((disponible - PLANCHE) / pas) + 1) : (disponible > 0 ? 1 : 0);
    var largeurs = [], passePieds = [];
    if (n <= 0){ return { n: 0, largeurs: largeurs, passePieds: passePieds }; }
    if (n === 1){
      largeurs = [Math.min(PLANCHE, disponible)];
    } else if (n === 2){
      // Ni l'une ni l'autre n'est intérieure : rien à qui laisser le
      // rattrapage « planches », tout va au passe-pied qui les sépare.
      largeurs = [BORD_PLANCHE, BORD_PLANCHE];
      passePieds = [Math.max(0, disponible - 2 * BORD_PLANCHE)];
    } else {
      var nInt = n - 2;
      var idealInt = nInt * PLANCHE + (n - 1) * PASSE_PIED;
      var reste = (disponible - 2 * BORD_PLANCHE) - idealInt;
      var pInt = PLANCHE, ppp = PASSE_PIED;
      if (rattrapage === 'planches'){ pInt = PLANCHE + reste / nInt; }
      else { ppp = PASSE_PIED + reste / (n - 1); }
      largeurs.push(BORD_PLANCHE);
      for (var i = 0; i < nInt; i++){ largeurs.push(pInt); }
      largeurs.push(BORD_PLANCHE);
      for (var j = 0; j < n - 1; j++){ passePieds.push(ppp); }
    }
    return { n: n, largeurs: largeurs, passePieds: passePieds };
  }

  function redessiner(){
    var longueur = Math.max(1, nombre(champLongueur.value));
    var largeur = Math.max(1, nombre(champLargeur.value));
    var b = bordures();
    var bi = bordureInterne();
    var ECH = 100; // 1 m = 100 unités svg
    var L = longueur * ECH, H = largeur * ECH;

    svg.setAttribute('viewBox', '0 0 ' + (L + 140) + ' ' + (H + 100));
    svg.querySelector('g').setAttribute('transform', 'translate(70,40)');
    svg.querySelector('.plan-parcelle-fond').setAttribute('width', L);
    svg.querySelector('.plan-parcelle-fond').setAttribute('height', H);

    var bn = b.nord * ECH, bs = b.sud * ECH, bo = b.ouest * ECH, be = b.est * ECH;
    var rn = document.getElementById('bordure-nord'), rs = document.getElementById('bordure-sud');
    var ro = document.getElementById('bordure-ouest'), re = document.getElementById('bordure-est');
    rn.setAttribute('width', L); rn.setAttribute('height', bn); rn.style.display = b.nord ? '' : 'none';
    rs.setAttribute('y', H - bs); rs.setAttribute('width', L); rs.setAttribute('height', bs); rs.style.display = b.sud ? '' : 'none';
    ro.setAttribute('height', H); ro.setAttribute('width', bo); ro.style.display = b.ouest ? '' : 'none';
    re.setAttribute('x', L - be); re.setAttribute('height', H); re.setAttribute('width', be); re.style.display = b.est ? '' : 'none';

    // Bordure interne — un anneau juste à l'intérieur de l'externe : chaque
    // côté s'arrête aux bordures externes des côtés voisins (jamais jusqu'au
    // bord de la parcelle), et les côtés est/ouest s'arrêtent aux côtés
    // nord/sud internes plutôt que de les recouvrir. Un côté dont l'externe
    // est inactive part du bord de la parcelle (largeur externe = 0). Phase 4.
    var bni = bi.nord * ECH, bsi = bi.sud * ECH, boi = bi.ouest * ECH, bei = bi.est * ECH;
    var rni = document.getElementById('bordure-interne-nord'), rsi = document.getElementById('bordure-interne-sud');
    var roi = document.getElementById('bordure-interne-ouest'), rei = document.getElementById('bordure-interne-est');
    var largeurAnneau = Math.max(0, L - bo - be);
    var hauteurAnneau = Math.max(0, H - bn - bni - bs - bsi);
    rni.setAttribute('x', bo); rni.setAttribute('y', bn); rni.setAttribute('width', largeurAnneau); rni.setAttribute('height', bni); rni.style.display = bi.nord ? '' : 'none';
    rsi.setAttribute('x', bo); rsi.setAttribute('y', H - bs - bsi); rsi.setAttribute('width', largeurAnneau); rsi.setAttribute('height', bsi); rsi.style.display = bi.sud ? '' : 'none';
    roi.setAttribute('x', bo); roi.setAttribute('y', bn + bni); roi.setAttribute('height', hauteurAnneau); roi.setAttribute('width', boi); roi.style.display = bi.ouest ? '' : 'none';
    rei.setAttribute('x', L - be - bei); rei.setAttribute('y', bn + bni); rei.setAttribute('height', hauteurAnneau); rei.setAttribute('width', bei); rei.style.display = bi.est ? '' : 'none';

    document.getElementById('mesure-longueur').setAttribute('x', L / 2);
    document.getElementById('mesure-longueur').textContent = fmt(longueur) + ' m';
    document.getElementById('fleche-longueur').setAttribute('x2', L);
    document.getElementById('fleche-largeur').setAttribute('y2', H);
    document.getElementById('mesure-largeur').setAttribute('y', H / 2);
    document.getElementById('mesure-largeur').setAttribute('transform', 'rotate(-90 -24 ' + (H / 2) + ')');
    document.getElementById('mesure-largeur').textContent = fmt(largeur) + ' m';

    // les quatre repères nord/sud/est/ouest, recentrés sur les nouveaux bords
    document.getElementById('cote-nord').setAttribute('x', L / 2);
    document.getElementById('cote-sud').setAttribute('x', L / 2);
    document.getElementById('cote-sud').setAttribute('y', H - 8);
    document.getElementById('cote-ouest').setAttribute('y', H / 2);
    document.getElementById('cote-ouest').setAttribute('transform', 'rotate(-90 20 ' + (H / 2) + ')');
    document.getElementById('cote-est').setAttribute('x', L - 20);
    document.getElementById('cote-est').setAttribute('y', H / 2);
    document.getElementById('cote-est').setAttribute('transform', 'rotate(90 ' + (L - 20) + ' ' + (H / 2) + ')');

    // planches, dans la zone utile — nord-sud par défaut (une planche par
    // largeur, juxtaposées d'ouest en est) ; est-ouest sur demande (une
    // planche par longueur, empilées du nord au sud). Dans un petit
    // potager syntropique, le sens compte peu — les strates émergentes
    // dominent la lumière quel que soit le sens des lignes (connaissances/
    // livre-bienvenue-en-syntropie/ch08-au-potager-syntropique.md) — mais
    // le choix reste au porteur, pour une parcelle pérenne ou une
    // préférence de terrain.
    var planches = document.getElementById('planches');
    planches.innerHTML = '';
    var largeurUtile = Math.max(0, longueur - b.ouest - b.est - bi.ouest - bi.est);
    var hauteurUtile = Math.max(0, largeur - b.nord - b.sud - bi.nord - bi.sud);
    var sensChoisi = document.querySelector('input[name="sens-planches"]:checked');
    var sens = sensChoisi ? sensChoisi.value : 'nord-sud';
    var rattrapageChoisi = document.querySelector('input[name="rattrapage"]:checked');
    var rattrapage = rattrapageChoisi ? rattrapageChoisi.value : 'passe-pieds';
    var disponible = sens === 'nord-sud' ? largeurUtile : hauteurUtile;
    var layout = planchesLayout(disponible, rattrapage);
    var nbPlanches = layout.n;

    if (sens === 'nord-sud'){
      var x = bo + boi;
      for (var i = 0; i < layout.largeurs.length; i++){
        var w = layout.largeurs[i] * ECH;
        var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('class', 'plan-planche');
        rect.setAttribute('x', x); rect.setAttribute('y', bn + bni);
        rect.setAttribute('width', w); rect.setAttribute('height', Math.max(0, H - bn - bni - bs - bsi));
        planches.appendChild(rect);
        x += w + (layout.passePieds[i] || 0) * ECH;
      }
    } else {
      var y = bn + bni;
      for (var j = 0; j < layout.largeurs.length; j++){
        var h = layout.largeurs[j] * ECH;
        var rect2 = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect2.setAttribute('class', 'plan-planche');
        rect2.setAttribute('x', bo + boi); rect2.setAttribute('y', y);
        rect2.setAttribute('width', Math.max(0, L - bo - boi - be - bei)); rect2.setAttribute('height', h);
        planches.appendChild(rect2);
        y += h + (layout.passePieds[j] || 0) * ECH;
      }
    }

    var surfaceUtile = largeurUtile * hauteurUtile;
    // La surface des planches elles-mêmes — les seules à cultiver,
    // passe-pieds exclus, contrairement à « Surface utile » ci-dessus qui
    // compte tout ce que les bordures laissent, passe-pieds compris.
    // Chaque planche d'un rang a la même profondeur (l'autre dimension
    // utile, perpendiculaire au sens des planches) : la somme des largeurs
    // du tableau suffit, pas besoin de reparcourir le dessin.
    var profondeur = sens === 'nord-sud' ? hauteurUtile : largeurUtile;
    var surfacePlanches = layout.largeurs.reduce(function(s, l){ return s + l; }, 0) * profondeur;
    var lineaire = (b.nord ? longueur : 0) + (b.sud ? longueur : 0) + (b.ouest ? largeur : 0) + (b.est ? largeur : 0);
    document.getElementById('r-surface').textContent = fmt(surfaceUtile) + ' m²';
    document.getElementById('r-surface-planches').textContent = fmt(surfacePlanches) + ' m²';
    document.getElementById('r-planches').textContent = nbPlanches;
    document.getElementById('r-planche-bord').textContent = layout.largeurs.length ? fmt(layout.largeurs[0]) + ' m' : '—';
    document.getElementById('r-planche-interieure').textContent = layout.largeurs.length > 2 ? fmt(layout.largeurs[1]) + ' m' : '—';
    document.getElementById('r-passe-pied').textContent = layout.passePieds.length ? fmt(layout.passePieds[0]) + ' m' : '—';
    document.getElementById('r-lineaire').textContent = fmt(lineaire) + ' m';
  }

  document.querySelectorAll('.bordure-active, .bordure-largeur, .bordure-interne-active, .bordure-interne-largeur, #in-longueur, #in-largeur, input[name="sens-planches"], input[name="rattrapage"]').forEach(function(el){
    el.addEventListener('input', redessiner);
    el.addEventListener('change', redessiner);
  });
  function champDeInterne(cote){ return document.querySelector('.bordure-interne-largeur[data-cote="' + cote + '"]'); }

  /* -------------------------------------------------------------- la bulle */
  var libellesCote = { nord: 'nord', sud: 'sud', ouest: 'ouest', est: 'est' };
  function texteBulle(rect){
    var interne = rect.classList.contains('plan-bordure-interne');
    var cote = libellesCote[rect.dataset.cote];
    var champ = interne ? champDeInterne(rect.dataset.cote) : champDe(rect.dataset.cote);
    var valeur = champ ? champ.value + ' m' : '';
    var mot = interne ? 'Bordure interne ' : 'Bordure ';
    if (!interne && document.getElementById('outil-deplacer').checked){
      return enCours ? mot + cote + ' — ' + valeur : 'Tirez pour changer la largeur — ' + cote + ', ' + valeur;
    }
    return mot + cote + ' — ' + valeur;
  }
  function montrerBulle(rect){
    var horizontal = rect.dataset.cote === 'nord' || rect.dataset.cote === 'sud';
    Gabarit.bulle.montrer(rect, texteBulle(rect), horizontal ? 'horizontal' : 'vertical');
  }
  // .plan-bordure seulement, pas .plan-bordure-interne : la bordure interne
  // se règle par ses champs dans le panneau, pas encore glissable sur le
  // plan — voir chantier-plan-de-parcelle.md § Phase 4, hors périmètre
  // assumé pour cette phase.
  document.querySelectorAll('.plan-bordure[data-cote]').forEach(function(rect){
    rect.addEventListener('pointerenter', function(){ if (!enCours) montrerBulle(rect); });
    rect.addEventListener('pointerleave', function(){ if (!enCours) Gabarit.bulle.cacher(); });
  });
  // La bordure interne reste survolable — juste pas glissable — pour lire
  // sa largeur d'un coup d'œil comme l'externe.
  document.querySelectorAll('.plan-bordure-interne[data-cote]').forEach(function(rect){
    rect.addEventListener('pointerenter', function(){ if (!enCours) montrerBulle(rect); });
    rect.addEventListener('pointerleave', function(){ if (!enCours) Gabarit.bulle.cacher(); });
  });

  /* --------------------------------------------------- glisser un bord */
  var enCours = null;
  // Le bord opposé, et la dimension de la parcelle qu'ils se partagent —
  // nord et sud dans la largeur, ouest et est dans la longueur. Une
  // bordure ne peut pas grandir au-delà de ce que l'autre lui laisse :
  // au-delà, elle sortirait de la parcelle plutôt que de la border.
  var OPPOSE = { nord: 'sud', sud: 'nord', ouest: 'est', est: 'ouest' };
  var CHAMP_DIMENSION = { nord: champLargeur, sud: champLargeur, ouest: champLongueur, est: champLongueur };
  function versMetres(xEcran, yEcran){
    var ctm = svg.getScreenCTM();
    if (!ctm) return null;
    var p = svg.createSVGPoint();
    p.x = xEcran; p.y = yEcran;
    return p.matrixTransform(ctm.inverse());
  }

  svg.addEventListener('pointerdown', function(e){
    if (!document.getElementById('outil-deplacer').checked) return;
    var rect = e.target.closest('.plan-bordure[data-cote]');
    if (!rect) return;
    var point = versMetres(e.clientX, e.clientY);
    if (!point) return;
    var cote = rect.dataset.cote;
    enCours = { rect: rect, cote: cote, depart: point, largeurDepart: nombre(champDe(cote).value) };
    rect.classList.add('en-glissement');
    montrerBulle(rect);
  });
  document.addEventListener('pointermove', function(e){
    if (!enCours) return;
    var point = versMetres(e.clientX, e.clientY);
    if (!point) return;
    var dx = (point.x - enCours.depart.x) / 100, dy = (point.y - enCours.depart.y) / 100;
    var delta = { nord: dy, sud: -dy, ouest: dx, est: -dx }[enCours.cote] || 0;
    var dimension = Math.max(1, nombre(CHAMP_DIMENSION[enCours.cote].value));
    var oppose = nombre(champDe(OPPOSE[enCours.cote]).value);
    var plafond = Math.max(0.1, dimension - oppose);
    var largeur = Math.min(plafond, Math.max(0.1, enCours.largeurDepart + delta));
    champDe(enCours.cote).value = fmt(largeur);
    redessiner();
    montrerBulle(enCours.rect);
  });
  document.addEventListener('pointerup', function(){
    if (!enCours) return;
    enCours.rect.classList.remove('en-glissement');
    enCours = null;
    Gabarit.bulle.cacher();
  });

  /* ----------------------------------------------- enregistrer le plan
     lui-même — longueur, largeur, bordures, sens des planches. Chantier
     « plan de parcelle », Phase 1 : jusqu'ici purement client, rien de
     tout cela ne survivait à un rechargement — Phase 2 y ajoute le calcul
     serveur de nb_planches, qui n'a donc plus besoin d'être saisi
     séparément (voir Parcelles::enregistrerPlan()). Construit à la main
     plutôt que via FormData : ces champs ne vivent pas dans un <form>
     (Entrée n'y déclenche donc jamais un envoi natif pendant la saisie). */
  var boutonPlan = document.getElementById('plan-bouton');
  if (boutonPlan){
    var erreurPlan = document.getElementById('plan-erreur');
    var etatPlan = document.getElementById('plan-etat');
    var champVersionPlan = document.getElementById('version-plan');
    boutonPlan.addEventListener('click', function(){
      boutonPlan.disabled = true;
      erreurPlan.hidden = true;
      etatPlan.hidden = true;
      var corps = new URLSearchParams();
      corps.set('action', 'plan');
      corps.set('csrf', document.getElementById('plan-csrf').value);
      corps.set('parcelle_id', document.getElementById('plan-parcelle-id').value);
      corps.set('version_plan', champVersionPlan.value);
      corps.set('conduite', document.getElementById('in-conduite').value);
      corps.set('stade', document.getElementById('in-stade').value);
      corps.set('tortue', document.getElementById('in-tortue').value);
      corps.set('longueur_m', champLongueur.value);
      corps.set('largeur_m', champLargeur.value);
      var sensChoisi = document.querySelector('input[name="sens-planches"]:checked');
      corps.set('orientation_lignes', sensChoisi ? sensChoisi.value : 'nord-sud');
      var rattrapageChoisi = document.querySelector('input[name="rattrapage"]:checked');
      corps.set('rattrapage', rattrapageChoisi ? rattrapageChoisi.value : 'passe-pieds');
      document.querySelectorAll('.bordure-active').forEach(function(c){
        var cote = c.dataset.cote;
        corps.set('bordures[' + cote + '][actif]', c.checked ? '1' : '');
        corps.set('bordures[' + cote + '][largeur_m]', champDe(cote).value);
      });
      document.querySelectorAll('.bordure-interne-active').forEach(function(c){
        var cote = c.dataset.cote;
        corps.set('bordures_interne[' + cote + '][actif]', c.checked ? '1' : '');
        corps.set('bordures_interne[' + cote + '][largeur_m]', champDeInterne(cote).value);
      });
      fetch(location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: corps
      })
        .then(function(r){ return r.json(); })
        .then(function(j){
          boutonPlan.disabled = false;
          if (j.ok){
            champVersionPlan.value = j.version;
            etatPlan.textContent = 'Plan enregistré.';
            etatPlan.hidden = false;
          } else {
            erreurPlan.textContent = Object.values(j.erreurs || {}).join(' ') || 'Erreur inattendue.';
            erreurPlan.hidden = false;
          }
        })
        .catch(function(){
          boutonPlan.disabled = false;
          erreurPlan.textContent = "L'enregistrement a échoué. Réessayez dans un moment.";
          erreurPlan.hidden = false;
        });
    });
  }

  /* --------------------------------------------------------- côtes on/off */
  document.getElementById('afficher-cotes').addEventListener('change', function(e){
    document.querySelectorAll('.plan-cote, .plan-mesure, .plan-fleche').forEach(function(el){
      el.style.display = e.target.checked ? '' : 'none';
    });
  });

  /* --------------------------------------- outil actif et réinitialiser */
  Gabarit.onOutilChange(function(id){
    bureau.classList.toggle('outil-deplacer-actif', id === 'outil-deplacer');
  });
  // Le résumé de la conduite suit le choix, sans ajouter de ligne au panneau.
  var choixConduite = document.getElementById('in-conduite');
  if (choixConduite){
    choixConduite.addEventListener('change', function(){
      document.getElementById('conduite-resume').textContent =
        choixConduite.options[choixConduite.selectedIndex].dataset.resume + ' Le vocabulaire des strates du scénario en dépend.';
    });
  }

  var choixTortue = document.getElementById('in-tortue');
  if (choixTortue){
    choixTortue.addEventListener('change', function(){
      document.getElementById('tortue-resume').textContent = choixTortue.options[choixTortue.selectedIndex].dataset.resume;
    });
  }

  var choixStade = document.getElementById('in-stade');
  if (choixStade){
    choixStade.addEventListener('change', function(){
      document.getElementById('stade-resume').textContent = choixStade.options[choixStade.selectedIndex].dataset.resume;
    });
  }

  Gabarit.onReset(function(){
    champLongueur.value = '12,00'; champLargeur.value = '8,00';
    document.querySelectorAll('.bordure-active').forEach(function(c){ c.checked = true; });
    document.querySelectorAll('.bordure-largeur').forEach(function(c){ c.value = '0,40'; });
    document.querySelectorAll('.bordure-interne-active').forEach(function(c){ c.checked = false; });
    document.querySelectorAll('.bordure-interne-largeur').forEach(function(c){ c.value = '0,40'; });
    document.getElementById('sens-nord-sud').checked = true;
    document.getElementById('rattrapage-passe-pieds').checked = true;
    redessiner();
  });

  redessiner();
})();
