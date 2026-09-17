/* Étape « Créer les parcelles » — propre à cette page, chargé après
   gabarit.js. Le gabarit (thème, tiroirs, Échap, menu d'outils, bulle)
   est déjà en place ; ce script ne s'occupe que de la géométrie. */
(function(){
  'use strict';

  var bureau = document.getElementById('bureau');
  var svg = document.getElementById('plan');
  var champLongueur = document.getElementById('in-longueur');
  var champLargeur = document.getElementById('in-largeur');
  var PLANCHE = 1.2, PASSE_PIED = 0.5;

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

  function redessiner(){
    var longueur = Math.max(1, nombre(champLongueur.value));
    var largeur = Math.max(1, nombre(champLargeur.value));
    var b = bordures();
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

    document.getElementById('mesure-longueur').setAttribute('x', L / 2);
    document.getElementById('mesure-longueur').textContent = fmt(longueur) + ' m';
    document.getElementById('fleche-longueur').setAttribute('x2', L);
    document.getElementById('fleche-largeur').setAttribute('y2', H);
    document.getElementById('mesure-largeur').setAttribute('y', H / 2);
    document.getElementById('mesure-largeur').setAttribute('transform', 'rotate(-90 -24 ' + (H / 2) + ')');
    document.getElementById('mesure-largeur').textContent = fmt(largeur) + ' m';

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
    var largeurUtile = Math.max(0, longueur - b.ouest - b.est);
    var hauteurUtile = Math.max(0, largeur - b.nord - b.sud);
    var pas = PLANCHE + PASSE_PIED;
    var sensChoisi = document.querySelector('input[name="sens-planches"]:checked');
    var sens = sensChoisi ? sensChoisi.value : 'nord-sud';
    var nbPlanches;
    if (sens === 'nord-sud'){
      nbPlanches = largeurUtile > PLANCHE ? Math.max(1, Math.floor((largeurUtile - PLANCHE) / pas) + 1) : (largeurUtile > 0 ? 1 : 0);
      var xDepart = b.ouest * ECH;
      for (var i = 0; i < nbPlanches; i++){
        var x = xDepart + i * pas * ECH;
        var w = Math.min(PLANCHE * ECH, (longueur - b.est) * ECH - x);
        if (w <= 0) break;
        var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('class', 'plan-planche');
        rect.setAttribute('x', x); rect.setAttribute('y', bn);
        rect.setAttribute('width', w); rect.setAttribute('height', Math.max(0, H - bn - bs));
        planches.appendChild(rect);
      }
    } else {
      nbPlanches = hauteurUtile > PLANCHE ? Math.max(1, Math.floor((hauteurUtile - PLANCHE) / pas) + 1) : (hauteurUtile > 0 ? 1 : 0);
      var yDepart = b.nord * ECH;
      for (var j = 0; j < nbPlanches; j++){
        var y = yDepart + j * pas * ECH;
        var h = Math.min(PLANCHE * ECH, (largeur - b.sud) * ECH - y);
        if (h <= 0) break;
        var rect2 = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect2.setAttribute('class', 'plan-planche');
        rect2.setAttribute('x', bo); rect2.setAttribute('y', y);
        rect2.setAttribute('width', Math.max(0, L - bo - be)); rect2.setAttribute('height', h);
        planches.appendChild(rect2);
      }
    }

    var surfaceUtile = largeurUtile * hauteurUtile;
    var lineaire = (b.nord ? longueur : 0) + (b.sud ? longueur : 0) + (b.ouest ? largeur : 0) + (b.est ? largeur : 0);
    document.getElementById('r-surface').textContent = fmt(surfaceUtile) + ' m²';
    document.getElementById('r-planches').textContent = nbPlanches;
    document.getElementById('r-lineaire').textContent = fmt(lineaire) + ' m';
  }

  document.querySelectorAll('.bordure-active, .bordure-largeur, #in-longueur, #in-largeur, input[name="sens-planches"]').forEach(function(el){
    el.addEventListener('input', redessiner);
    el.addEventListener('change', redessiner);
  });

  /* -------------------------------------------------------------- la bulle */
  var libellesCote = { nord: 'nord', sud: 'sud', ouest: 'ouest', est: 'est' };
  function texteBulle(rect){
    var cote = libellesCote[rect.dataset.cote];
    var champ = champDe(rect.dataset.cote);
    var valeur = champ ? champ.value + ' m' : '';
    if (document.getElementById('outil-deplacer').checked){
      return enCours ? 'Bordure ' + cote + ' — ' + valeur : 'Tirez pour changer la largeur — ' + cote + ', ' + valeur;
    }
    return 'Bordure ' + cote + ' — ' + valeur;
  }
  function montrerBulle(rect){
    var horizontal = rect.dataset.cote === 'nord' || rect.dataset.cote === 'sud';
    Gabarit.bulle.montrer(rect, texteBulle(rect), horizontal ? 'horizontal' : 'vertical');
  }
  document.querySelectorAll('rect[data-cote]').forEach(function(rect){
    rect.addEventListener('pointerenter', function(){ if (!enCours) montrerBulle(rect); });
    rect.addEventListener('pointerleave', function(){ if (!enCours) Gabarit.bulle.cacher(); });
  });

  /* --------------------------------------------------- glisser un bord */
  var enCours = null;
  function versMetres(xEcran, yEcran){
    var ctm = svg.getScreenCTM();
    if (!ctm) return null;
    var p = svg.createSVGPoint();
    p.x = xEcran; p.y = yEcran;
    return p.matrixTransform(ctm.inverse());
  }

  svg.addEventListener('pointerdown', function(e){
    if (!document.getElementById('outil-deplacer').checked) return;
    var rect = e.target.closest('rect[data-cote]');
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
    var largeur = Math.max(0.1, enCours.largeurDepart + delta);
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

  /* ------------------------------------- enregistrer les mesures réelles
     (pour la carte du terrain) sans recharger la page — un rechargement
     perdrait tout le plan ci-dessus, purement client (voir en-tête du
     fichier PHP) : longueur/largeur, bordures, sens des planches, rien
     de tout cela n'est encore en base. */
  var formDimensions = document.getElementById('dimensions-form');
  if (formDimensions){
    var boutonDimensions = document.getElementById('dimensions-bouton');
    var erreurDimensions = document.getElementById('dimensions-erreur');
    var etatDimensions = document.getElementById('dimensions-etat');
    formDimensions.addEventListener('submit', function(e){
      e.preventDefault();
      boutonDimensions.disabled = true;
      erreurDimensions.hidden = true;
      etatDimensions.hidden = true;
      // location.href, jamais `formDimensions.action` : le champ caché
      // nommé "action" (POST vers ce même script) masque la propriété
      // native du formulaire, qui renverrait alors cet input, non l'URL.
      fetch(location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(formDimensions)
      })
        .then(function(r){ return r.json(); })
        .then(function(j){
          boutonDimensions.disabled = false;
          if (j.ok){
            etatDimensions.textContent = 'Enregistré — pour la carte du terrain, sans effet sur le plan ci-dessus.';
            etatDimensions.hidden = false;
          } else {
            erreurDimensions.textContent = (j.erreurs || []).join(' ') || 'Erreur inattendue.';
            erreurDimensions.hidden = false;
          }
        })
        .catch(function(){
          boutonDimensions.disabled = false;
          erreurDimensions.textContent = "L'enregistrement a échoué. Réessayez dans un moment.";
          erreurDimensions.hidden = false;
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
  Gabarit.onReset(function(){
    champLongueur.value = '12,00'; champLargeur.value = '8,00';
    document.querySelectorAll('.bordure-active').forEach(function(c){ c.checked = true; });
    document.querySelectorAll('.bordure-largeur').forEach(function(c){ c.value = '0,40'; });
    document.getElementById('sens-nord-sud').checked = true;
    redessiner();
  });

  redessiner();
})();
