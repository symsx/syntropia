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
  // Le nombre de lignes de chaque planche — ce qui est enregistré, puis ce que la personne change ici (chantier
  // « peuplement de la parcelle », Phase 4). Une planche sans valeur prend le défaut.
  var LIGNES_DEFAUT = cfgPlan.lignesDefaut || 3, LIGNES_MIN = cfgPlan.lignesMin || 1, LIGNES_MAX = cfgPlan.lignesMax || 12;
  var lignesSaisies = {};
  Object.keys(cfgPlan.lignes || {}).forEach(function(k){ lignesSaisies[k] = cfgPlan.lignes[k]; });
  function lignesDe(numero){
    var n = parseInt(lignesSaisies[numero], 10);
    return (n >= LIGNES_MIN && n <= LIGNES_MAX) ? n : LIGNES_DEFAUT;
  }
  // La nature des planches (tortues zèbre et abeille — Phase 5) : la première planche ou bande, puis l'alternance ;
  // en zèbre, une planche peut être changée. Même règle que Planches::natures() côté serveur.
  var naturesSaisies = {};
  Object.keys(cfgPlan.natures || {}).forEach(function(k){ naturesSaisies[k] = cfgPlan.natures[k]; });
  var choixPremiere = document.getElementById('in-premiere-nature');
  function tortue(){ var t = document.getElementById('in-tortue'); return t ? t.value : ''; }
  function premiere(){ return choixPremiere ? choixPremiere.value : 'perenne'; }
  function autreNature(n){ return n === 'perenne' ? 'annuelle' : 'perenne'; }
  function natureParDefaut(numero){ return numero % 2 === 1 ? premiere() : autreNature(premiere()); }
  function natureDe(numero){
    var v = naturesSaisies[numero];
    return (v === 'perenne' || v === 'annuelle') ? v : natureParDefaut(numero);
  }
  // La ligne centrale : celle du milieu, ou la plus proche — même règle que Planches::centrale().
  function centrale(n){ return Math.floor((n - 1) / 2); }
  var signatureListe = '';
  function blocNatures(sens){
    var bloc = document.getElementById('natures-bloc');
    if (!bloc) return;
    var t = tortue();
    bloc.hidden = t !== 'zebre' && t !== 'abeille';
    var ou = sens === 'nord-sud' ? 'à l’ouest' : 'au nord';
    document.getElementById('premiere-libelle').textContent = t === 'abeille' ? 'La première bande (' + ou + ')' : 'La première planche (' + ou + ')';
    document.getElementById('natures-note').textContent = t === 'abeille'
      ? 'Tortue abeille : chaque planche porte une bande pérenne et une bande potagère ; choisissez la première, les bandes alternent ensuite. La bande pérenne est teintée sur le plan.'
      : 'Tortue zèbre : des planches pérennes alternent avec des planches annuelles ; choisissez la première, les autres alternent — chacune se change ensuite ci-dessus. Les planches pérennes sont teintées sur le plan.';
  }
  function listeLignes(largeurs){
    var zone = document.getElementById('lignes-planches');
    if (!zone) return;
    var signature = largeurs.map(function(w){ return w.toFixed(2); }).join('|') + '#' + tortue() + premiere();
    if (signature === signatureListe) return;   // ne pas reconstruire sous les doigts de la personne
    signatureListe = signature;
    zone.innerHTML = '';
    largeurs.forEach(function(w, i){
      var numero = i + 1;
      var label = document.createElement('label');
      var texte = document.createElement('span');
      texte.innerHTML = 'Planche ' + numero + ' <small>' + fmt(w) + ' m</small>';
      var champ = document.createElement('input');
      champ.type = 'number'; champ.min = LIGNES_MIN; champ.max = LIGNES_MAX; champ.step = 1;
      champ.value = lignesDe(numero); champ.className = 'lignes-planche'; champ.dataset.numero = numero;
      champ.setAttribute('aria-label', 'Nombre de lignes de la planche ' + numero);
      champ.addEventListener('input', function(){ lignesSaisies[numero] = champ.value; redessiner(); });
      label.appendChild(texte); label.appendChild(champ);
      if (tortue() === 'zebre'){
        label.classList.add('lp-zebre');
        var nature = document.createElement('select');
        nature.className = 'nature-planche'; nature.dataset.numero = numero;
        nature.setAttribute('aria-label', 'Nature de la planche ' + numero);
        [['perenne', 'Pérenne'], ['annuelle', 'Annuelle']].forEach(function(o){
          var opt = document.createElement('option'); opt.value = o[0]; opt.textContent = o[1];
          if (natureDe(numero) === o[0]) opt.selected = true;
          nature.appendChild(opt);
        });
        nature.addEventListener('change', function(){ naturesSaisies[numero] = nature.value; redessiner(); });
        label.appendChild(nature);
      }
      zone.appendChild(label);
    });
  }

  // Où court la ligne des lianes, en pixels du dessin : au milieu du passe-pied central (`coeur`), ou le long du bord nord
  // ou est de la zone des planches — au milieu du passe-pied du bord s'il y en a un. Planches::ligneDesLianes().
  function positionLiane(){ var c = document.getElementById('in-position-liane'); return c ? c.value : 'coeur'; }
  function ligneDesLianes(sens, layout, gauche, haut, lu, hu, ech){
    var ns = sens === 'nord-sud', debut = layout.debut * ech, p = positionLiane();
    if (p === 'coeur'){
      var pos = (ns ? gauche : haut) + debut;
      if (layout.n >= 2){
        var k = Math.floor((layout.n - 2) / 2);
        for (var i = 0; i <= k; i++){ pos += layout.largeurs[i] * ech + (i < k ? layout.passePieds[i] * ech : 0); }
        pos += layout.passePieds[k] * ech / 2;
      } else if (debut > 0){
        pos = (ns ? gauche : haut) + debut / 2;
      }
      return ns ? [pos, haut, pos, haut + hu] : [gauche, pos, gauche + lu, pos];
    }
    if (p === 'nord'){
      var y = !ns && debut > 0 ? haut + debut / 2 : haut;
      return [gauche, y, gauche + lu, y];
    }
    var x = ns && debut > 0 ? gauche + lu - debut / 2 : gauche + lu;
    return [x, haut, x, haut + hu];
  }

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
  // Chaque demi-planche a son passe-pied (le porteur, 2026-09-29). Sans passe-pieds le long des bords, les planches des
  // deux bords ne font qu'une demi-planche ; avec (`bords`), un passe-pied avant la première et après la dernière, et
  // toutes à PLANCHE. Une planche seule est centrée. `debut` : la place avant la première planche. Même calcul que
  // Planches::disposition() côté serveur — les deux doivent rester identiques.
  function planchesLayout(disponible, rattrapage, bords){
    if (disponible <= 0){ return { n: 0, largeurs: [], passePieds: [], debut: 0 }; }
    if (disponible <= PLANCHE){ return { n: 1, largeurs: [disponible], passePieds: [], debut: 0 }; }
    var i, largeurs = [], passePieds = [];
    if (bords){
      var nb = Math.max(1, Math.floor((disponible - PASSE_PIED) / (PLANCHE + PASSE_PIED) + 1e-9));
      var resteB = disponible - nb * PLANCHE - (nb + 1) * PASSE_PIED;
      var pl = PLANCHE, pp;
      if (resteB < 0){ pp = (disponible - PLANCHE) / 2; }
      else if (rattrapage === 'planches'){ pl = PLANCHE + resteB / nb; pp = PASSE_PIED; }
      else { pp = PASSE_PIED + resteB / (nb + 1); }
      for (i = 0; i < nb; i++){ largeurs.push(pl); }
      for (i = 0; i < nb - 1; i++){ passePieds.push(pp); }
      return { n: nb, largeurs: largeurs, passePieds: passePieds, debut: pp };
    }
    // Deux demi-planches aux bords, n − 2 planches entières, n − 1 passe-pieds (2026-09-29).
    var n = Math.max(1, Math.floor((disponible - 2 * BORD_PLANCHE + 2 * PLANCHE + PASSE_PIED) / (PLANCHE + PASSE_PIED) + 1e-9));
    if (n === 1){ return { n: 1, largeurs: [PLANCHE], passePieds: [], debut: (disponible - PLANCHE) / 2 }; }
    if (n === 2){
      // Ni l'une ni l'autre n'est intérieure : rien à qui laisser le
      // rattrapage « planches », tout va au passe-pied qui les sépare.
      return { n: 2, largeurs: [BORD_PLANCHE, BORD_PLANCHE], passePieds: [Math.max(0, disponible - 2 * BORD_PLANCHE)], debut: 0 };
    }
    var nInt = n - 2;
    var reste = (disponible - 2 * BORD_PLANCHE) - (nInt * PLANCHE + (n - 1) * PASSE_PIED);
    var pInt = PLANCHE, ppp = PASSE_PIED;
    if (rattrapage === 'planches'){ pInt = PLANCHE + reste / nInt; }
    else { ppp = PASSE_PIED + reste / (n - 1); }
    largeurs.push(BORD_PLANCHE);
    for (i = 0; i < nInt; i++){ largeurs.push(pInt); }
    largeurs.push(BORD_PLANCHE);
    for (i = 0; i < n - 1; i++){ passePieds.push(ppp); }
    return { n: n, largeurs: largeurs, passePieds: passePieds, debut: 0 };
  }
  function passePiedsBords(){ var c = document.getElementById('passe-pieds-bords'); return !!(c && c.checked); }

  /* ------------------------------------------------------------- le zoom
     Le plan entier tient toujours dans le bureau (z = 1) ; zoomer resserre
     le viewBox autour d'un centre, sans rien redessiner — tout est déjà en
     unités du plan. Le cadre prend les proportions du bureau, pas celles de
     la parcelle : agrandi, le plan occupe toute la largeur (ou la hauteur)
     de l'écran. Dans un sens où le plan déborde du cadre, le cadre ne sort
     pas de la parcelle ; dans l'autre, le plan reste centré. */
  var ZOOM_MAX = 8;
  var vue = { l: 1240, h: 900, z: 1, cx: 620, cy: 450 };
  function borner(centre, cadre, etendue){
    return cadre >= etendue ? etendue / 2 : Math.min(etendue - cadre / 2, Math.max(cadre / 2, centre));
  }
  function appliquerVue(){
    vue.z = Math.min(ZOOM_MAX, Math.max(1, vue.z));
    var boite = svg.getBoundingClientRect();
    var ratio = boite.width > 0 && boite.height > 0 ? boite.width / boite.height : vue.l / vue.h;
    var w0 = Math.max(vue.l, vue.h * ratio);
    var w = w0 / vue.z, h = w / ratio;
    vue.cx = borner(vue.cx, w, vue.l);
    vue.cy = borner(vue.cy, h, vue.h);
    svg.setAttribute('viewBox', (vue.cx - w / 2) + ' ' + (vue.cy - h / 2) + ' ' + w + ' ' + h);
    bureau.classList.toggle('plan-zoome', vue.z > 1);
    var moins = document.getElementById('plan-zoom-moins'), plus = document.getElementById('plan-zoom-plus');
    var tout = document.getElementById('plan-zoom-tout');
    if (moins){ moins.disabled = vue.z <= 1; plus.disabled = vue.z >= ZOOM_MAX; tout.hidden = vue.z <= 1; }
    majSigles();
  }
  // Zoomer d'un facteur en gardant sous le pointeur le point qui s'y trouvait
  // (au centre du plan pour les boutons).
  function zoomer(facteur, xEcran, yEcran){
    var avant = vue.z;
    var p = xEcran === undefined ? null : versMetres(xEcran, yEcran);
    vue.z = Math.min(ZOOM_MAX, Math.max(1, vue.z * facteur));
    if (vue.z === avant) return;
    if (p){
      var k = avant / vue.z;
      vue.cx = p.x + (vue.cx - p.x) * k;
      vue.cy = p.y + (vue.cy - p.y) * k;
    }
    appliquerVue();
  }

  function redessiner(){
    var longueur = Math.max(1, nombre(champLongueur.value));
    var largeur = Math.max(1, nombre(champLargeur.value));
    var b = bordures();
    var bi = bordureInterne();
    var ECH = 100; // 1 m = 100 unités svg
    var L = longueur * ECH, H = largeur * ECH;

    vue.l = L + 140; vue.h = H + 100;
    appliquerVue();
    svg.querySelector('g').setAttribute('transform', 'translate(70,40)');
    svg.querySelector('.plan-parcelle-fond').setAttribute('width', L);
    svg.querySelector('.plan-parcelle-fond').setAttribute('height', H);

    var bn = b.nord * ECH, bs = b.sud * ECH, bo = b.ouest * ECH, be = b.est * ECH;
    var rn = document.getElementById('bordure-nord'), rs = document.getElementById('bordure-sud');
    var ro = document.getElementById('bordure-ouest'), re = document.getElementById('bordure-est');
    document.querySelectorAll('.bordure-brise-vent').forEach(function(c){
      document.getElementById('bordure-' + c.dataset.cote).classList.toggle('brise-vent', c.checked);
    });
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
    var layout = planchesLayout(disponible, rattrapage, passePiedsBords());
    var nbPlanches = layout.n;

    if (sens === 'nord-sud'){
      var x = bo + boi + layout.debut * ECH;
      for (var i = 0; i < layout.largeurs.length; i++){
        var w = layout.largeurs[i] * ECH;
        var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('class', 'plan-planche' + (tortue() === 'zebre' && natureDe(i + 1) === 'perenne' ? ' plan-planche-perenne' : ''));
        rect.setAttribute('x', x); rect.setAttribute('y', bn + bni);
        rect.setAttribute('width', w); rect.setAttribute('height', Math.max(0, H - bn - bni - bs - bsi));
        planches.appendChild(rect);
        if (tortue() === 'abeille'){
          var bandeNS = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
          bandeNS.setAttribute('class', 'plan-bande-perenne');
          bandeNS.setAttribute('x', premiere() === 'perenne' ? x : x + w / 2); bandeNS.setAttribute('y', bn + bni);
          bandeNS.setAttribute('width', w / 2); bandeNS.setAttribute('height', Math.max(0, H - bn - bni - bs - bsi));
          planches.appendChild(bandeNS);
        }
        x += w + (layout.passePieds[i] || 0) * ECH;
      }
    } else {
      var y = bn + bni + layout.debut * ECH;
      for (var j = 0; j < layout.largeurs.length; j++){
        var h = layout.largeurs[j] * ECH;
        var rect2 = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect2.setAttribute('class', 'plan-planche' + (tortue() === 'zebre' && natureDe(j + 1) === 'perenne' ? ' plan-planche-perenne' : ''));
        rect2.setAttribute('x', bo + boi); rect2.setAttribute('y', y);
        rect2.setAttribute('width', Math.max(0, L - bo - boi - be - bei)); rect2.setAttribute('height', h);
        planches.appendChild(rect2);
        if (tortue() === 'abeille'){
          var bandeEO = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
          bandeEO.setAttribute('class', 'plan-bande-perenne');
          bandeEO.setAttribute('x', bo + boi); bandeEO.setAttribute('y', premiere() === 'perenne' ? y : y + h / 2);
          bandeEO.setAttribute('width', Math.max(0, L - bo - boi - be - bei)); bandeEO.setAttribute('height', h / 2);
          planches.appendChild(bandeEO);
        }
        y += h + (layout.passePieds[j] || 0) * ECH;
      }
    }

    // Les lignes de chaque planche, à intervalles égaux dans sa largeur (Planches::dUneParcelle() côté serveur).
    var gLignes = document.getElementById('lignes-plan');
    if (gLignes){
      gLignes.innerHTML = '';
      var depart = (sens === 'nord-sud' ? bo + boi : bn + bni) + layout.debut * ECH;
      var haut = bn + bni, bas = H - bs - bsi, gauche = bo + boi, droite = L - be - bei;
      layout.largeurs.forEach(function(w, i){
        var n = lignesDe(i + 1), largeurPx = w * ECH;
        for (var j = 0; j < n; j++){
          var d = depart + largeurPx * (j + 1) / (n + 1);
          var trait = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          trait.setAttribute('class', 'plan-ligne' + (j === centrale(n) ? ' plan-ligne-centrale' : ''));
          if (sens === 'nord-sud'){ trait.setAttribute('x1', d); trait.setAttribute('x2', d); trait.setAttribute('y1', haut); trait.setAttribute('y2', bas); }
          else { trait.setAttribute('y1', d); trait.setAttribute('y2', d); trait.setAttribute('x1', gauche); trait.setAttribute('x2', droite); }
          gLignes.appendChild(trait);
        }
        depart += largeurPx + (layout.passePieds[i] || 0) * ECH;
      });
    }
    // La ligne des lianes (tortue liane) — même règle que Planches::ligneDesLianes() côté serveur.
    if (gLignes && tortue() === 'liane' && layout.n >= 1){
      var geo = ligneDesLianes(sens, layout, bo + boi, bn + bni, largeurUtile * ECH, hauteurUtile * ECH, ECH);
      var tl = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      tl.setAttribute('class', 'plan-ligne-liane');
      tl.setAttribute('x1', geo[0]); tl.setAttribute('y1', geo[1]); tl.setAttribute('x2', geo[2]); tl.setAttribute('y2', geo[3]);
      gLignes.appendChild(tl);
    }
    var blocLiane = document.getElementById('liane-bloc');
    if (blocLiane) blocLiane.hidden = tortue() !== 'liane';
    listeLignes(layout.largeurs);
    blocNatures(sens);

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
    var passePiedAffiche = layout.passePieds.length ? layout.passePieds[0] : (passePiedsBords() && layout.debut > 0 ? layout.debut : 0);
    document.getElementById('r-passe-pied').textContent = passePiedAffiche ? fmt(passePiedAffiche) + ' m' : '—';
    document.getElementById('r-lineaire').textContent = fmt(lineaire) + ' m';
  }

  /* ------------------------------------------ les plants des bordures
     Posés sur le plan ENREGISTRÉ (chantier « peuplement de la parcelle »,
     Phase 2) : le serveur les compte et les place, ce script les dessine.
     Dès que la géométrie change à l'écran sans être enregistrée, ils ne
     correspondent plus — on les masque et on suspend leur saisie, plutôt
     que de montrer des plants au mauvais endroit. */
  var cfgPlants = (typeof PLANTS_BORDURES !== 'undefined') ? PLANTS_BORDURES : { plants: [], couleurs: [] };
  var plantsPerimes = false;
  (function dessinerPlants(){
    var g = document.getElementById('plantations');
    if (!g) return;
    // Un aperçu de répartition (rien d'enregistré) se dessine pâli.
    if (cfgPlants.apercu) g.classList.add('plan-apercu');
    var SVG = 'http://www.w3.org/2000/svg';
    function couleur(p){ var e = (cfgPlants.especes || [])[p[2]]; return cfgPlants.couleurs[e ? e.couleur : 0]; }
    function disque(p, classe, r){
      var c = document.createElementNS(SVG, 'circle');
      c.setAttribute('class', classe);
      c.setAttribute('cx', p[0] * 100); c.setAttribute('cy', p[1] * 100);
      c.setAttribute('r', r);
      c.setAttribute('fill', couleur(p));
      c.setAttribute('stroke', couleur(p)); // le point central le remplace par du blanc (CSS)
      g.appendChild(c);
      return c;
    }
    // Une espèce dont l'envergure adulte est connue (Espèces, « Largeur adulte ») se dessine par un disque transparent,
    // pour voir où les plants se recouvrent, et un point à l'endroit où l'on plante. L'envergure de la fiche est celle
    // d'une plante en croissance libre (15 m pour un tilleul) : en bordure, taillée, elle ne l'atteint jamais — le
    // disque est borné à deux fois l'espacement (décision du porteur, 2026-10-01). Sans envergure connue, une pastille
    // proportionnée à la bande, comme avant.
    function envergureDessinee(p){ return p[5] ? Math.min(p[4], 2 * p[5]) : p[4]; }
    cfgPlants.plants.filter(function(p){ return p[4]; }).forEach(function(p){ disque(p, 'plan-plant-envergure', envergureDessinee(p) * 100 / 2); });
    cfgPlants.plants.forEach(function(p){
      disque(p, 'plan-plant', p[4] ? 3 : Math.max(3, Math.min(9, p[3] * 100 / 2.5)));
    });
    // Le sigle de chaque plant (Sigles.php), montré quand le zoom laisse la place de le lire (majSigles).
    cfgPlants.plants.forEach(function(p){
      var e = (cfgPlants.especes || [])[p[2]];
      if (!e) return;
      var s = document.createElementNS(SVG, 'text');
      s.setAttribute('class', 'plan-sigle');
      s.setAttribute('x', p[0] * 100); s.setAttribute('y', p[1] * 100);
      s.dataset.espacement = p[5] || p[3];
      s.dataset.espece = p[2];
      s.textContent = e.sigle;
      g.appendChild(s);
    });
    // Le nom au survol (ou au toucher) : une zone de la moitié de l'espacement autour du point, sans quoi le point, de
    // quelques pixels, serait trop petit pour être visé ; inactive pendant « Déplacer un bord » (CSS).
    cfgPlants.plants.forEach(function(p){
      var e = (cfgPlants.especes || [])[p[2]];
      if (!e) return;
      var c = document.createElementNS(SVG, 'circle');
      c.setAttribute('class', 'plan-plant-cible');
      c.setAttribute('cx', p[0] * 100); c.setAttribute('cy', p[1] * 100);
      c.setAttribute('r', Math.max(6, (p[5] || p[3]) * 100 / 2));
      var texte = e.sigle + ' — ' + e.nom + (p[5] ? ' — ' + fmt(p[5]).replace(/,?0+$/, '') + ' m' : '')
        + ((cfgPlants.cotes || [])[p[6]] ? ' — ' + cfgPlants.cotes[p[6]].toLowerCase() : '');
      c.addEventListener('pointerenter', function(){ Gabarit.bulle.montrer(c, texte, 'horizontal'); });
      c.addEventListener('pointerleave', function(){ Gabarit.bulle.cacher(); });
      g.appendChild(c);
    });
    majSigles();
    // Sur téléphone, la légende couvrirait le plan : repliée par défaut, elle s'ouvre d'un toucher.
    var legende = document.getElementById('plan-legende');
    if (legende && window.matchMedia('(max-width: 40rem)').matches) legende.open = false;
    // Ouvrir ou fermer la légende change la place du plan (CSS) : il se recadre.
    if (legende) legende.addEventListener('toggle', appliquerVue);
  })();
  /* Les sigles se lisent à taille fixe à l'écran (11 px), et seulement là où le plant laisse assez de place à ce zoom :
     sur un plan vu en entier, à 25 ou 40 cm d'écart, ils se chevaucheraient. Rappelée à chaque changement de vue. */
  function majSigles(){
    var g = document.getElementById('plantations');
    if (!g || !svg) return;
    var boite = svg.getBoundingClientRect(), vb = svg.viewBox && svg.viewBox.baseVal;
    if (!vb || !vb.width || !boite.width) return;
    var pixelsParUnite = boite.width / vb.width;
    g.querySelectorAll('.plan-sigle').forEach(function(s){
      s.setAttribute('font-size', 11 / pixelsParUnite);
      s.setAttribute('stroke-width', 3 / pixelsParUnite);
      // Au premier dessin, les préférences ne sont pas encore lues : tout est montré.
      var a = affichage || { sigles: true, masques: [] };
      var voulu = a.sigles && a.masques.indexOf(nomEspece(s.dataset.espece)) < 0;
      s.style.display = voulu && parseFloat(s.dataset.espacement) * 100 * pixelsParUnite >= 22 ? '' : 'none';
    });
  }

  /* Ce que la légende commande (le porteur, 2026-10-01) : les sigles sur le plan, tous ou aucun (« Sigles sur le
     plan »), et espèce par espèce (sa pastille) ; et la légende elle-même (« Afficher la légende », au pied). Un
     confort de lecture, propre à ce navigateur : gardé dans `localStorage` pour chaque parcelle, les espèces repérées
     par leur nom (leur sigle peut changer quand la parcelle en reçoit d'autres). Sans stockage, rien ne se garde. */
  var cleAffichage = 'syntropia_plan_affichage_' + (document.getElementById('plan-parcelle-id') || {}).value;
  var affichage = { sigles: true, legende: true, masques: [] };
  try { var lu = JSON.parse(localStorage.getItem(cleAffichage) || 'null'); if (lu) { affichage.sigles = lu.sigles !== false; affichage.legende = lu.legende !== false; affichage.masques = Array.isArray(lu.masques) ? lu.masques : []; } } catch (e) {}
  function garderAffichage(){ try { localStorage.setItem(cleAffichage, JSON.stringify(affichage)); } catch (e) {} }
  function nomEspece(indice){ var e = (cfgPlants.especes || [])[indice]; return e ? e.nom : ''; }
  (function legende(){
    var details = document.getElementById('plan-legende');
    var tous = document.getElementById('sigles-tous');
    var montrer = document.getElementById('afficher-legende');
    if (!details) return;
    var pastilles = Array.prototype.slice.call(details.querySelectorAll('.plan-legende-pastille'));
    function majLegende(){
      pastilles.forEach(function(b){ b.setAttribute('aria-pressed', affichage.masques.indexOf(b.dataset.nom) < 0 ? 'true' : 'false'); });
      var visibles = pastilles.filter(function(b){ return b.getAttribute('aria-pressed') === 'true'; }).length;
      if (tous){ tous.checked = affichage.sigles && visibles > 0; tous.indeterminate = affichage.sigles && visibles > 0 && visibles < pastilles.length; }
      details.hidden = !affichage.legende;
      if (montrer) montrer.checked = affichage.legende;
      majSigles();
    }
    pastilles.forEach(function(b){
      b.addEventListener('click', function(){
        var i = affichage.masques.indexOf(b.dataset.nom);
        if (i < 0) affichage.masques.push(b.dataset.nom); else affichage.masques.splice(i, 1);
        if (i >= 0) affichage.sigles = true;   // rendre un sigle, c'est vouloir des sigles
        garderAffichage(); majLegende();
      });
    });
    if (tous) tous.addEventListener('change', function(){
      // Tous, ou aucun : « tous » efface aussi les masques espèce par espèce.
      affichage.sigles = tous.checked;
      if (tous.checked) affichage.masques = [];
      garderAffichage(); majLegende();
    });
    if (montrer) montrer.addEventListener('change', function(){
      affichage.legende = montrer.checked; garderAffichage(); majLegende(); appliquerVue();
    });
    majLegende();
  })();
  function perimerPlants(){
    if (plantsPerimes) return;
    plantsPerimes = true;
    var g = document.getElementById('plantations');
    if (g) g.classList.add('plan-perime');
    var note = document.getElementById('bl-suspendu');
    if (note) note.hidden = false;
    document.querySelectorAll('.bl-cote').forEach(function(d){ d.classList.add('bl-suspendu'); });
  }
  if (cfgPlants.ouvrir){
    var aOuvrir = document.getElementById(cfgPlants.ouvrir) || document.getElementById('bl-titre');
    var tirette = document.getElementById('tirette');
    if (aOuvrir && tirette){
      tirette.click();
      setTimeout(function(){ aOuvrir.scrollIntoView({ block: aOuvrir.id === 'rb' ? 'start' : 'center' }); }, 350);
    }
  }

  // La coche « brise-vent » ne change pas la géométrie : elle redessine, sans périmer les plants.
  document.querySelectorAll('.bordure-brise-vent').forEach(function(el){ el.addEventListener('change', redessiner); });
  document.querySelectorAll('.bordure-active, .bordure-largeur, .bordure-interne-active, .bordure-interne-largeur, #in-longueur, #in-largeur, input[name="sens-planches"], input[name="rattrapage"], #passe-pieds-bords, #in-position-liane').forEach(function(el){
    el.addEventListener('input', redessiner);
    el.addEventListener('change', redessiner);
    el.addEventListener('input', perimerPlants);
    el.addEventListener('change', perimerPlants);
  });
  function champDeInterne(cote){ return document.querySelector('.bordure-interne-largeur[data-cote="' + cote + '"]'); }

  /* -------------------------------------------------------------- la bulle */
  var libellesCote = { nord: 'nord', sud: 'sud', ouest: 'ouest', est: 'est' };
  function texteBulle(rect){
    var interne = rect.classList.contains('plan-bordure-interne');
    var cote = libellesCote[rect.dataset.cote];
    var champ = interne ? champDeInterne(rect.dataset.cote) : champDe(rect.dataset.cote);
    var valeur = champ ? champ.value + ' m' : '';
    var vent = interne ? null : document.querySelector('.bordure-brise-vent[data-cote="' + rect.dataset.cote + '"]');
    var mot = interne ? 'Bordure interne ' : (vent && vent.checked ? 'Bordure brise-vent ' : 'Bordure ');
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
    perimerPlants();
    montrerBulle(enCours.rect);
  });
  document.addEventListener('pointerup', function(){
    if (!enCours) return;
    enCours.rect.classList.remove('en-glissement');
    enCours = null;
    Gabarit.bulle.cacher();
  });

  /* ----------------------------------------- zoomer et parcourir le plan
     La molette zoome sous le pointeur ; un plan agrandi se fait glisser —
     sauf quand « Déplacer un bord » saisit une bordure (écouteur posé plus
     haut, donc servi avant celui-ci). */
  svg.addEventListener('wheel', function(e){
    e.preventDefault();
    zoomer(e.deltaY < 0 ? 1.25 : 0.8, e.clientX, e.clientY);
  }, { passive: false });

  var parcours = null;
  svg.addEventListener('pointerdown', function(e){
    if (enCours || vue.z <= 1 || e.button !== 0) return;
    var ctm = svg.getScreenCTM();
    if (!ctm) return;
    parcours = { x: e.clientX, y: e.clientY, cx: vue.cx, cy: vue.cy, unite: 1 / ctm.a };
    bureau.classList.add('plan-parcouru');
    e.preventDefault();
  });
  document.addEventListener('pointermove', function(e){
    if (!parcours) return;
    vue.cx = parcours.cx - (e.clientX - parcours.x) * parcours.unite;
    vue.cy = parcours.cy - (e.clientY - parcours.y) * parcours.unite;
    appliquerVue();
  });
  document.addEventListener('pointerup', function(){
    if (!parcours) return;
    parcours = null;
    bureau.classList.remove('plan-parcouru');
  });

  var zoomPlus = document.getElementById('plan-zoom-plus');
  if (zoomPlus){
    zoomPlus.addEventListener('click', function(){ zoomer(1.5); });
    document.getElementById('plan-zoom-moins').addEventListener('click', function(){ zoomer(1 / 1.5); });
    document.getElementById('plan-zoom-tout').addEventListener('click', function(){ vue.z = 1; appliquerVue(); });
  }
  // Le cadre suit les proportions du bureau : une fenêtre redimensionnée le recalcule.
  window.addEventListener('resize', appliquerVue);

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
      if (document.getElementById('in-position-liane')) corps.set('liane', positionLiane());
      document.querySelectorAll('.lignes-planche').forEach(function(c){ corps.set('lignes[' + c.dataset.numero + ']', c.value); });
      if (choixPremiere){
        corps.set('natures[premiere]', premiere());
        // Seules les planches changées par rapport à l'alternance s'enregistrent.
        document.querySelectorAll('.nature-planche').forEach(function(c){
          var n = parseInt(c.dataset.numero, 10);
          if (c.value !== natureParDefaut(n)) corps.set('natures[planches][' + n + ']', c.value);
        });
      }
      corps.set('longueur_m', champLongueur.value);
      corps.set('largeur_m', champLargeur.value);
      var sensChoisi = document.querySelector('input[name="sens-planches"]:checked');
      corps.set('orientation_lignes', sensChoisi ? sensChoisi.value : 'nord-sud');
      var rattrapageChoisi = document.querySelector('input[name="rattrapage"]:checked');
      corps.set('rattrapage', rattrapageChoisi ? rattrapageChoisi.value : 'passe-pieds');
      corps.set('passe_pieds_bords', passePiedsBords() ? '1' : '0');
      document.querySelectorAll('.bordure-active').forEach(function(c){
        var cote = c.dataset.cote;
        corps.set('bordures[' + cote + '][actif]', c.checked ? '1' : '');
        corps.set('bordures[' + cote + '][largeur_m]', champDe(cote).value);
        var vent = document.querySelector('.bordure-brise-vent[data-cote="' + cote + '"]');
        corps.set('bordures[' + cote + '][brise_vent]', vent && vent.checked ? '1' : '');
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
            // La géométrie a changé : les lignes des bordures se recomptent sur
            // le plan qui vient d'être écrit — la page se recharge pour les montrer.
            if (plantsPerimes){ location.href = location.pathname + '?parcelle=' + encodeURIComponent(document.getElementById('plan-parcelle-id').value) + '&bordure=' + encodeURIComponent('recompte'); return; }
            champVersionPlan.value = j.version;
            document.dispatchEvent(new Event('plan-enregistre'));
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
      redessiner();
      // La ligne des lianes n'existe qu'en tortue liane : les lignes se recomptent sur le plan enregistré.
      perimerPlants();
    });
  }
  if (choixPremiere){ choixPremiere.addEventListener('change', redessiner); }

  var choixStade = document.getElementById('in-stade');
  if (choixStade){
    choixStade.addEventListener('change', function(){
      document.getElementById('stade-resume').textContent = choixStade.options[choixStade.selectedIndex].dataset.resume;
    });
  }

  /* Annuler et réinitialiser (le porteur, 2026-10-01) : deux gestes, chacun confirmé. ANNULER revient au plan tel
     qu'il est enregistré — défaire ce qui a changé à l'écran ; RÉINITIALISER repart des valeurs par défaut de l'atelier.
     Ni l'un ni l'autre n'écrit rien : seul « Enregistrer le plan » écrit. « Ce qui a changé » se lit en comparant les
     champs du plan (ceux du panneau qui ne sont dans aucun formulaire) à leur état au chargement ou au dernier
     enregistrement. */
  var boutonAnnuler = document.getElementById('pied-annuler');
  function etatDesChamps(){
    var champs = [];
    document.querySelectorAll('#panneau-droit input, #panneau-droit select').forEach(function(c){
      if (c.closest('form') || c.type === 'hidden') return;
      champs.push(c.type === 'checkbox' || c.type === 'radio' ? (c.checked ? '1' : '0') : c.value);
    });
    return champs.join('\u0001');
  }
  var etatEnregistre = etatDesChamps();
  function majAnnuler(){ if (boutonAnnuler) boutonAnnuler.disabled = etatDesChamps() === etatEnregistre; }
  document.getElementById('panneau-droit').addEventListener('input', majAnnuler);
  document.getElementById('panneau-droit').addEventListener('change', majAnnuler);
  document.addEventListener('plan-enregistre', function(){ etatEnregistre = etatDesChamps(); majAnnuler(); });

  Gabarit.onAnnuler(function(){
    if (etatDesChamps() === etatEnregistre) return;
    Gabarit.confirmer({
      titre: 'Annuler les changements ?',
      texte: 'Le plan revient tel qu’il est enregistré : ce que vous avez modifié depuis, et pas encore enregistré, est perdu.',
      oui: 'Annuler les changements', non: 'Continuer à modifier'
    }).then(function(ok){
      if (ok) location.href = location.pathname + '?parcelle=' + encodeURIComponent(document.getElementById('plan-parcelle-id').value);
    });
  });

  Gabarit.onReset(function(){
    Gabarit.confirmer({
      titre: 'Réinitialiser le plan ?',
      texte: 'Le plan repart des valeurs par défaut de l’atelier : 12 × 8 m, bordure externe sur les quatre côtés à 0,40 m, '
        + 'sans coche « vent », sans bordure interne, planches nord-sud. Rien n’est écrit tant que vous ne cliquez pas '
        + '« Enregistrer le plan » — « Annuler les changements » vous ramène sinon au plan enregistré.',
      oui: 'Réinitialiser', non: 'Garder mon plan'
    }).then(function(ok){ if (ok) reinitialiser(); });
  });

  function reinitialiser(){
    perimerPlants();
    lignesSaisies = {}; naturesSaisies = {}; signatureListe = '';
    champLongueur.value = '12,00'; champLargeur.value = '8,00';
    document.querySelectorAll('.bordure-active').forEach(function(c){ c.checked = true; });
    document.querySelectorAll('.bordure-largeur').forEach(function(c){ c.value = '0,40'; });
    document.querySelectorAll('.bordure-brise-vent').forEach(function(c){ c.checked = false; });
    document.querySelectorAll('.bordure-interne-active').forEach(function(c){ c.checked = false; });
    document.querySelectorAll('.bordure-interne-largeur').forEach(function(c){ c.value = '0,40'; });
    document.getElementById('sens-nord-sud').checked = true;
    document.getElementById('rattrapage-passe-pieds').checked = true;
    document.getElementById('passe-pieds-bords').checked = false;
    if (document.getElementById('in-position-liane')) document.getElementById('in-position-liane').value = 'coeur';
    redessiner();
    majAnnuler();
  }

  redessiner();
  // L'état de référence se prend après le premier dessin, qui crée certains champs (les lignes de chaque planche).
  etatEnregistre = etatDesChamps();
  majAnnuler();
})();
