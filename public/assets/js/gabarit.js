/* Le gabarit — la coquille commune à toutes les étapes de l'atelier.
   Charge etapes.js avant celui-ci. Chaque page appelle Gabarit.init({
   site, etape }) une fois son HTML en place ; le reste (thème, tiroirs,
   Échap, menu d'outils, bulle) est générique et ne dépend d'aucun
   contenu propre à l'étape. Voir architecture-technique.md. */
window.Gabarit = (function(){
  'use strict';

  function el(id){ return document.getElementById(id); }

  function etapeParId(id){
    return (window.ETAPES || []).filter(function(e){ return e.id === id; })[0];
  }

  var fermetures = [];   // une fonction "fermer" par tiroir, pour Échap
  var ecouteursOutil = [];
  var bulle, bureau;
  var pageDuParcours = false;   // vrai si la page porte une étape de etapes.js

  function init(config){
    config = config || {};
    var etape = etapeParId(config.etape);
    pageDuParcours = !!etape;
    if (!etape) {
      // Une page hors parcours (accueil, connexion...) n'a légitimement
      // pas d'étape : avertir seulement si une étape a été demandée et
      // ne correspond à rien dans etapes.js — pas quand elle est absente.
      if (config.etape) {
        console.warn('Gabarit.init : étape inconnue — "' + config.etape + '". Voir public/assets/js/etapes.js');
      }
    } else {
      remplirTitreEtNavigation(config.site, etape, config.contexte);
      remplirPhaseProjet(etape);
      remplirEtapesPhase(etape);
    }

    remplirSectionDocumentation();

    initTheme();
    initTiroirGauche();
    initTiroirDroit();
    initTiroirInfo();
    initOutils();
    initCompteMenu();
    initProjets();
    initEchap();
    initTermes();
    initAvisPerime();

    return api;
  }

  /* -------------------------------------------------- documentation */
  /* Deux parties, toujours en dernière position du panneau gauche, sur
     TOUTE page — publique ou connectée, avec ou sans étape. Un seul
     endroit à tenir à jour plutôt qu'une section « Documentation »
     recopiée à la main sur chaque page (même raison que
     remplirEtapesPhase() : voir CLAUDE.md § Mécanique à respecter). */
  var DOCUMENTATION = [
    { id: 'documentation-saf',  titre: "L'agroforesterie syntropique", pret: true },
    { id: 'documentation-site', titre: "L'atelier",                    pret: true },
    { id: 'glossaire',          titre: "Glossaire",                    pret: true },
    { id: 'documentation-sources', titre: "Les sources",               pret: true }
  ];

  function remplirSectionDocumentation(){
    var corps = document.querySelector('.pg-corps');
    if (!corps) return;
    var ici = (window.location.pathname.split('/').pop() || '');

    var section = document.createElement('div');
    section.className = 'pg-section';
    section.id = 'pg-section-documentation';
    var titre = document.createElement('p');
    titre.className = 'pg-titre';
    titre.textContent = 'Documentation';
    section.appendChild(titre);

    var liste = document.createElement('ul');
    liste.className = 'pg-liste';
    DOCUMENTATION.forEach(function(d){
      var li = document.createElement('li');
      var a = document.createElement('a');
      if (ici === d.id + '.php') {
        a.href = '#';
        a.className = 'active';
        a.setAttribute('aria-current', 'page');
      } else if (d.pret) {
        a.href = d.id + '.php';
      } else {
        a.href = '#';
        a.className = 'a-venir';
        a.tabIndex = -1;
        a.setAttribute('aria-disabled', 'true');
      }
      a.innerHTML = '<span class="pg-puce">?</span><span class="pg-libelle">' + d.titre + '</span>';
      li.appendChild(a);
      liste.appendChild(li);
    });
    section.appendChild(liste);

    var pied = corps.querySelector('.pg-pied');
    if (pied) { corps.insertBefore(section, pied); } else { corps.appendChild(section); }
  }

  /* ------------------------------------------------ titre et navigation */
  function remplirTitreEtNavigation(site, etape, contexte){
    var titre = document.querySelector('.bg-centre-titre');
    if (titre) titre.textContent = (site ? site + ' — ' : '') + etape.titre + (contexte ? ' · ' + contexte : '');

    var index = window.ETAPES.indexOf(etape);
    configurerBoutonEtape(el('etape-precedente'), window.ETAPES[index - 1]);
    configurerBoutonEtape(el('etape-suivante'), window.ETAPES[index + 1]);
  }

  function configurerBoutonEtape(bouton, cible){
    if (!bouton) return;
    // Grisé quand il n'y a rien avant/après, mais aussi quand l'étape
    // voisine existe dans le manifeste sans que sa page soit construite
    // — jamais de lien mort, montré comme tout autre "à venir" du site.
    if (!cible || !cible.pret){ bouton.disabled = true; return; }
    bouton.disabled = false;
    bouton.addEventListener('click', function(){ window.location.href = cible.id + '.php'; });
  }

  /* -------------------------------------------------------- phase projet */
  /* Chaque phase mène à sa première étape construite — sans quoi
     « Définition » restait un href="#" mort dès qu'on était passé en
     Conception, seule porte fermée derrière soi trouvée par le porteur
     le 2026-09-14. Pas de mémoire de la dernière étape visitée : aucune
     persistance n'existe encore pour la porter. */
  function premiereEtapePreteDeLaPhase(phaseId){
    return (window.ETAPES || []).filter(function(e){ return e.phase === phaseId && e.pret; })[0];
  }

  function remplirPhaseProjet(etapeActive){
    var liste = document.querySelector('.pg-phase-projet');
    if (!liste || !window.PHASES) return;
    var indexActif = window.PHASES.map(function(p){ return p.id; }).indexOf(etapeActive.phase);
    liste.innerHTML = '';
    window.PHASES.forEach(function(phase, i){
      var li = document.createElement('li');
      var a = document.createElement('a');
      var premiere = premiereEtapePreteDeLaPhase(phase.id);
      if (i === indexActif){
        a.href = '#';
        a.className = 'active';
        a.setAttribute('aria-current', 'page');
      } else if (premiere){
        a.href = premiere.id + '.php';
      } else {
        a.href = '#';
        a.className = 'a-venir';
        a.tabIndex = -1;
        a.setAttribute('aria-disabled', 'true');
      }
      a.innerHTML = '<span class="pg-puce">' + phase.numero + '</span><span class="pg-libelle">' + phase.libelle + '</span>';
      li.appendChild(a);
      liste.appendChild(li);
    });
  }

  /* -------------------------------------------------- étapes de la phase */
  /* La liste des étapes de la phase en cours, dans l'ordre du parcours
     (jamais par fréquence — voir CLAUDE.md § Ordonner les menus).
     Construite depuis etapes.js pour qu'une page n'ait jamais à tenir à
     jour la liste de ses voisines : le manifeste est la seule source. */
  function remplirEtapesPhase(etapeActive){
    var liste = document.querySelector('.pg-etapes-phase');
    if (!liste || !window.ETAPES) return;
    var etapesPhase = window.ETAPES.filter(function(e){ return e.phase === etapeActive.phase; });
    liste.innerHTML = '';
    etapesPhase.forEach(function(e, i){
      var li = document.createElement('li');
      var a = document.createElement('a');
      if (e.id === etapeActive.id) {
        a.href = '#';
        a.className = 'active';
        a.setAttribute('aria-current', 'page');
      } else if (e.pret) {
        a.href = e.id + '.php';
      } else {
        a.href = '#';
        a.className = 'a-venir';
        a.tabIndex = -1;
        a.setAttribute('aria-disabled', 'true');
      }
      a.innerHTML = '<span class="pg-puce">' + (i + 1) + '</span><span class="pg-libelle">' + e.titre + '</span>';
      li.appendChild(a);
      liste.appendChild(li);
    });
  }

  /* ------------------------------------------------------ thème clair/sombre */
  /* Trois états, pas deux : « système » suit le réglage du navigateur
     (`prefers-color-scheme`, en CSS) et c'est le défaut — aucun cookie ni
     `localStorage` posé tant qu'on ne s'en écarte pas. Le bouton doit
     montrer lequel des trois est actif, pas seulement permettre d'en
     changer — voir le porteur, 2026-09-14 : « dans syntropie l'icône
     changeait de motif ». Mêmes glyphes que `application/src/Theme.php`
     (dépôt de référence) : ☀ clair, ☾ sombre, ◐ système. */
  var GLYPHES_THEME = { clair: '☀', sombre: '☾', systeme: '◐' };
  var LIBELLES_THEME = { clair: 'clair', sombre: 'sombre', systeme: 'système' };

  function initTheme(){
    var bascule = el('theme-bascule');
    if (!bascule) return;
    var icone = el('theme-icone');
    var cle = 'syntropie-theme';
    function appliquer(valeur){
      if (valeur === 'clair') document.documentElement.setAttribute('data-theme', 'light');
      else if (valeur === 'sombre') document.documentElement.setAttribute('data-theme', 'dark');
      else document.documentElement.removeAttribute('data-theme');
      if (icone) icone.textContent = GLYPHES_THEME[valeur] || GLYPHES_THEME.systeme;
      var libelle = LIBELLES_THEME[valeur] || LIBELLES_THEME.systeme;
      bascule.title = 'Thème ' + libelle + '. Cliquez pour changer.';
      bascule.setAttribute('aria-label', 'Thème ' + libelle + ', changer de thème');
    }
    var depart = 'systeme';
    try { depart = localStorage.getItem(cle) || 'systeme'; } catch (e) {}
    appliquer(depart);
    bascule.addEventListener('click', function(){
      var actuel = document.documentElement.getAttribute('data-theme');
      var suivant = actuel === 'dark' ? 'clair' : (actuel === 'light' ? 'systeme' : 'sombre');
      appliquer(suivant);
      try { localStorage.setItem(cle, suivant); } catch (e) {}
    });
  }

  /* ----------------------------------------------------- tiroirs génériques */
  function tiroir(idDeclencheur, idPanneau, idScrim, libelleAria){
    var declencheur = el(idDeclencheur), panneau = el(idPanneau), scrim = el(idScrim);
    if (!declencheur || !panneau || !scrim) return;
    function ouvrir(etat){
      declencheur.setAttribute('aria-expanded', etat ? 'true' : 'false');
      declencheur.classList.toggle('ouvert', etat);
      if (libelleAria) declencheur.setAttribute('aria-label', etat ? libelleAria.masquer : libelleAria.afficher);
      panneau.classList.toggle('ouvert', etat);
      panneau.setAttribute('aria-hidden', etat ? 'false' : 'true');
      scrim.classList.toggle('visible', etat);
    }
    declencheur.addEventListener('click', function(){ ouvrir(!panneau.classList.contains('ouvert')); });
    scrim.addEventListener('click', function(){ ouvrir(false); });
    fermetures.push(function(){ ouvrir(false); });
  }

  function initTiroirGauche(){ tiroir('hamburger', 'panneau-gauche', 'scrim-gauche'); }
  function initTiroirDroit(){
    tiroir('tirette', 'panneau-droit', 'scrim-droit', { afficher: 'Afficher le panneau Données', masquer: 'Masquer le panneau Données' });
  }
  function initTiroirInfo(){
    tiroir('tirette-info', 'panneau-info', 'scrim-info', { afficher: 'Afficher les informations', masquer: 'Masquer les informations' });
  }

  /* ------------------------------------------------------------ outils */
  function initOutils(){
    var bandeau = el('bandeau-outils');
    if (!bandeau) return;
    var declencheur = el('outils-declencheur');
    var liste = el('outils-liste');
    var icone = el('outils-icone-actif');
    var texte = el('outils-texte-actif');

    function ouvrirMenu(etat){
      if (!declencheur || !liste) return;
      declencheur.setAttribute('aria-expanded', etat ? 'true' : 'false');
      liste.classList.toggle('ouvert', etat);
    }
    if (declencheur && liste) {
      declencheur.addEventListener('click', function(){ ouvrirMenu(!liste.classList.contains('ouvert')); });
      document.addEventListener('click', function(e){
        if (!liste.classList.contains('ouvert')) return;
        if (liste.contains(e.target) || declencheur.contains(e.target)) return;
        ouvrirMenu(false);
      });
      fermetures.push(function(){ ouvrirMenu(false); });
    }

    function majOutil(){
      var actif = bandeau.querySelector('input:checked');
      if (!actif || !actif.nextElementSibling) return;
      var correspondance = actif.nextElementSibling.textContent.match(/^(\S+)\s(.*)$/);
      if (icone) icone.textContent = correspondance ? correspondance[1] : '';
      if (texte) texte.textContent = correspondance ? correspondance[2] : actif.nextElementSibling.textContent;
      ouvrirMenu(false);
      ecouteursOutil.forEach(function(fn){ fn(actif.id); });
    }
    bandeau.addEventListener('change', majOutil);
    majOutil();
  }

  /* -------------------------------------------------------- menu compte */
  /* Même mécanique que le menu d'outils : un déclencheur, une liste
     ouverte/fermée par classe, close au clic dehors et à Échap. */
  function initCompteMenu(){
    var bouton = el('compte-bouton');
    var menu = el('compte-menu');
    if (!bouton || !menu) return;
    function ouvrir(etat){
      bouton.setAttribute('aria-expanded', etat ? 'true' : 'false');
      menu.classList.toggle('ouvert', etat);
    }
    bouton.addEventListener('click', function(e){
      e.stopPropagation();
      ouvrir(!menu.classList.contains('ouvert'));
    });
    document.addEventListener('click', function(e){
      if (!menu.classList.contains('ouvert')) return;
      if (menu.contains(e.target) || bouton.contains(e.target)) return;
      ouvrir(false);
    });
    fermetures.push(function(){ ouvrir(false); });
  }

  /* ------------------------------------------------------- les projets */
  /* Un compte peut porter plusieurs projets (le sien, ceux rejoints sur
     invitation). Rien de cela n'est dans le HTML des pages : api-projets.php
     dit lesquels, et ce code pose trois choses — « Nouveau projet »,
     « Rejoindre un projet », « Espèces », « Inviter » et, pour un porteur, « Participants » dans le menu du compte, et, seulement s'il y en a plusieurs, la section « Mes
     projets » en tête du panneau gauche. Une page publique (pas de menu de
     compte) n'appelle rien. */
  /* La section « Dossier Projet », juste avant « Documentation » (chantier-dossier-projet.md) : le projet de la
     personne à imprimer. Elle n'a de sens que connecté, avec un projet ouvert — initProjets() le sait. Le dossier
     lui-même est hors gabarit (un document papier, comme releve-vierge.php). */
  function ajouterSectionDossier(){
    var doc = el('pg-section-documentation');
    if (!doc || el('pg-section-dossier')) return;
    var section = document.createElement('div');
    section.className = 'pg-section';
    section.id = 'pg-section-dossier';
    var titre = document.createElement('p');
    titre.className = 'pg-titre';
    titre.textContent = 'Dossier Projet';
    section.appendChild(titre);
    var liste = document.createElement('ul');
    liste.className = 'pg-liste';
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.href = 'dossier-projet.php';
    a.innerHTML = '<span class="pg-puce">⎙</span><span class="pg-libelle">Imprimer le dossier</span>';
    li.appendChild(a);
    liste.appendChild(li);
    section.appendChild(liste);
    doc.parentNode.insertBefore(section, doc);
  }

  function initProjets(){
    var menu = el('compte-menu');
    if (!menu || !window.fetch) return;
    fetch('api-projets.php', { credentials: 'same-origin' })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(j){
        if (!j || !j.ok) return;
        surveillerDeploiement(j.deploiement);
        var actif = j.projets.filter(function(p){ return p.id === j.actif; })[0];
        ajouterEntreesDuMenu(menu, !!actif && actif.role === 'porteur', !!j.administrateur);
        if (actif) ajouterSectionDossier();
        if (j.projets.length > 1) ajouterSelecteurProjets(j);
        if (actif && actif.role === 'lecteur' && pageDuParcours) lectureSeule();
      })
      .catch(function(){ /* le sélecteur est un confort : jamais une erreur visible */ });
  }

  /* ------------------------------------------- les mises à jour */
  /* Après une mise à jour de l'atelier, un envoi venu d'une page ouverte avant est refusé par le serveur (le jeton porte
     la marque du déploiement — chantier-mises-a-jour.md, Phase 4). Deux avis, pour que rien ne se perde en silence :
     avant l'envoi, si le déploiement en ligne a changé depuis le chargement de la page ; après, si le serveur a refusé
     un envoi (il pose alors un cookie, que la page suivante lit et efface). */
  var COOKIE_PERIME = 'syntropia_perime';
  var marqueChargee = null, avisAffiche = false;

  function avisVersion(texte, recharger){
    if (avisAffiche) return;
    avisAffiche = true;
    var a = document.createElement('div');
    a.className = 'avis-version';
    a.setAttribute('role', 'alert');
    var p = document.createElement('p');
    p.textContent = texte;
    a.appendChild(p);
    if (recharger){
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'avis-version-bouton'; b.textContent = 'Recharger la page';
      b.addEventListener('click', function(){ location.reload(); });
      a.appendChild(b);
    }
    var x = document.createElement('button');
    x.type = 'button'; x.className = 'avis-version-fermer'; x.setAttribute('aria-label', "Fermer l'avis"); x.textContent = '✕';
    x.addEventListener('click', function(){ a.remove(); });
    a.appendChild(x);
    document.body.appendChild(a);
  }

  function initAvisPerime(){
    if (!/(?:^|;\s*)syntropia_perime=1(?:;|$)/.test(document.cookie)) return;
    document.cookie = COOKIE_PERIME + '=; max-age=0; path=' + location.pathname.replace(/[^\/]*$/, '');
    avisVersion("Votre dernier envoi venait d'une page ouverte avant une mise à jour de l'atelier : il n'a pas été "
      + "enregistré. Cette page est à jour : refaites votre saisie.", false);
  }

  function surveillerDeploiement(marque){
    if (marqueChargee !== null || !marque) return;
    marqueChargee = marque;
    function verifier(){
      if (avisAffiche || document.visibilityState !== 'visible') return;
      fetch('api-projets.php', { credentials: 'same-origin' })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(j){
          if (j && j.ok && j.deploiement && j.deploiement !== marqueChargee){
            avisVersion("Une nouvelle version de l'atelier vient d'être installée. Rechargez la page avant "
              + "d'enregistrer : un envoi depuis cette page ne serait pas pris en compte.", true);
          }
        })
        .catch(function(){ /* un confort : jamais une erreur visible */ });
    }
    document.addEventListener('visibilitychange', verifier);
    setInterval(verifier, 5 * 60 * 1000);
  }

  /* Un lecteur regarde le projet. Le serveur refuse de toute façon ses
     écritures (Reponses, Parcelles, api-*) ; ceci ne fait que le dire, et
     éviter qu'on remplisse un formulaire pour rien. Les champs des
     formulaires de saisie sont grisés — « Continuer » reste actif, la
     navigation continue, et les onglets (`name=onglet`, du CSS pur) restent
     cliquables —, et le plan de parcelle reste explorable : ce
     qu'on y dessine n'est enregistré que par son bouton, grisé ici.
     **Pages du parcours seulement** : « Nouveau projet » et « Inviter »
     n'écrivent pas dans le projet regardé, et un lecteur doit pouvoir
     ouvrir le sien ou inviter — un champ grisé n'est pas envoyé. */
  function lectureSeule(){
    var titre = document.querySelector('.bg-centre-titre');
    // En tête : sur un petit écran le titre se tronque par la fin, et un
    // repère placé là disparaîtrait.
    if (titre) titre.textContent = 'Lecture seule — ' + titre.textContent;
    document.querySelectorAll('#bureau form input:not([type=hidden]):not([type=submit]):not([name=onglet]), #bureau form textarea, #bureau form select')
      .forEach(function(c){ c.disabled = true; });
    var plan = el('plan-bouton');
    if (plan) plan.disabled = true;
  }

  function ajouterEntreesDuMenu(menu, estPorteur, estAdministrateur){
    var sep = menu.querySelector('.compte-menu-separateur');
    var entrees = [['definition-projet.php?nouveau=1', '+', 'Nouveau projet'],
                   ['rejoindre.php', '⇥', 'Rejoindre un projet'],
                   ['importer.php', '⇧', 'Importer un projet'],
                   ['especes.php', '❧', 'Espèces'],
                   ['inviter.php', '✉', 'Inviter']];
    // La liste de ceux qui ont accès au projet actif, et son export (le fichier porte les
    // réponses de tous) : pour son porteur seulement.
    if (estPorteur) {
      entrees.push(['membres.php', '☰', 'Participants']);
      entrees.push(['exporter.php', '⇩', 'Exporter le projet']);
    }
    // Qui se connecte à l'atelier : pour un administrateur de l'atelier seulement.
    if (estAdministrateur) entrees.push(['journal-connexions.php', '◷', 'Journal des connexions']);
    entrees.forEach(function(e){
      var a = document.createElement('a');
      a.className = 'compte-menu-item';
      a.href = e[0];
      a.innerHTML = '<span class="compte-menu-puce">' + e[1] + '</span>' + e[2];
      menu.insertBefore(a, sep || null);
    });
  }

  var LIBELLE_ROLE = { porteur: 'porteur', contributeur: 'contributeur', lecteur: 'lecteur' };

  function ajouterSelecteurProjets(j){
    var corps = document.querySelector('.pg-corps');
    if (!corps) return;
    var section = document.createElement('div');
    section.className = 'pg-section';
    section.innerHTML = '<p class="pg-titre">Mes projets</p>';
    var liste = document.createElement('ul');
    liste.className = 'pg-liste';
    j.projets.forEach(function(p){
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = '#';
      var actif = p.id === j.actif;
      if (actif){ a.className = 'active'; a.setAttribute('aria-current', 'true'); }
      var puce = document.createElement('span');
      puce.className = 'pg-puce';
      puce.textContent = actif ? '●' : '○';
      var libelle = document.createElement('span');
      libelle.className = 'pg-libelle';
      libelle.textContent = p.nom + (p.role !== 'porteur' ? ' — ' + (LIBELLE_ROLE[p.role] || p.role) : '');
      a.appendChild(puce);
      a.appendChild(libelle);
      a.addEventListener('click', function(e){
        e.preventDefault();
        if (actif) return;
        var corpsPost = new URLSearchParams();
        corpsPost.set('csrf', j.csrf);
        corpsPost.set('site_id', p.id);
        fetch('api-projets.php', { method: 'POST', body: corpsPost, credentials: 'same-origin' })
          .then(function(r){ return r.ok ? r.json() : null; })
          .then(function(rep){
            // Sans la requête : un `?parcelle=` du projet quitté n'existe
            // pas dans le nouveau.
            if (rep && rep.ok) window.location.href = window.location.pathname;
          });
      });
      li.appendChild(a);
      liste.appendChild(li);
    });
    section.appendChild(liste);
    corps.insertBefore(section, corps.firstChild);
  }

  function initEchap(){
    document.addEventListener('keydown', function(e){
      if (e.key !== 'Escape') return;
      fermetures.forEach(function(fermer){ fermer(); });
    });
  }

  /* -------------------------------------------------------------- la bulle */
  /* positionne une bulle contre l'élément visé, du côté où la place ne
     manque pas dans le bureau. orientation force 'horizontal'|'vertical'
     quand la forme de l'élément ne suffit pas à le déduire. */
  function bulleMontrer(cible, texte, orientation){
    bulle = bulle || el('bulle');
    bureau = bureau || el('bureau');
    if (!bulle || !bureau) return;
    var b = cible.getBoundingClientRect();
    var bureauRect = bureau.getBoundingClientRect();
    var horizontal = orientation ? orientation === 'horizontal' : b.width >= b.height;
    var cote, x, y;
    if (horizontal) {
      var milieu = bureauRect.top + bureauRect.height / 2;
      cote = (b.top < milieu) ? 'bas' : 'haut';
      x = b.left + b.width / 2; y = (cote === 'haut') ? b.top : b.bottom;
    } else {
      var centre = bureauRect.left + bureauRect.width / 2;
      cote = (b.left < centre) ? 'droite' : 'gauche';
      x = (cote === 'droite') ? b.right : b.left; y = b.top + b.height / 2;
    }
    bulle.textContent = texte;
    bulle.dataset.coteBulle = cote;
    bulle.style.left = x + 'px';
    bulle.style.top = y + 'px';
    bulle.classList.add('visible');
  }
  function bulleCacher(){ if (bulle) bulle.classList.remove('visible'); }

  /* -------------------------------------------------------------- termes */
  /* Un mot technique (.terme, data-explique="...") montre sa bulle au
     survol ou au focus clavier — jamais le `title` natif, illisible au
     clavier et sans style. Générique, sur TOUTE page : aucune page n'a
     à câbler ça elle-même, comme la section Documentation. */
  function initTermes(){
    var termes = document.querySelectorAll('.terme[data-explique]');
    termes.forEach(function(t){
      if (t.tabIndex < 0) t.tabIndex = 0;
      t.addEventListener('mouseenter', function(){ bulleMontrer(t, t.dataset.explique); });
      t.addEventListener('mouseleave', bulleCacher);
      t.addEventListener('focus', function(){ bulleMontrer(t, t.dataset.explique); });
      t.addEventListener('blur', bulleCacher);
    });
  }

  /* ---------------------------------------------- la confirmation */
  /* Une question avant un geste qui défait quelque chose : une fenêtre du bureau (`<dialog>`, sans dépendance), deux
     boutons, Échap ou un clic à côté répondent « non ». Rend une promesse : vrai si la personne confirme. */
  function confirmer(o){
    return new Promise(function(resoudre){
      var d = document.createElement('dialog');
      d.className = 'confirmation';
      var titre = document.createElement('p'); titre.className = 'confirmation-titre'; titre.textContent = o.titre || '';
      var texte = document.createElement('p'); texte.className = 'confirmation-texte'; texte.textContent = o.texte || '';
      var boutons = document.createElement('div'); boutons.className = 'confirmation-boutons';
      var non = document.createElement('button'); non.type = 'button'; non.className = 'confirmation-non'; non.textContent = o.non || 'Annuler';
      var oui = document.createElement('button'); oui.type = 'button'; oui.className = 'confirmation-oui'; oui.textContent = o.oui || 'Confirmer';
      boutons.appendChild(non); boutons.appendChild(oui);
      d.appendChild(titre); d.appendChild(texte); d.appendChild(boutons);
      document.body.appendChild(d);
      var reponse = false;
      oui.addEventListener('click', function(){ reponse = true; d.close(); });
      non.addEventListener('click', function(){ d.close(); });
      d.addEventListener('click', function(e){ if (e.target === d) d.close(); });   // un clic sur le fond
      d.addEventListener('close', function(){ d.remove(); resoudre(reponse); });
      d.showModal();
      non.focus();   // le geste prudent par défaut : Entrée ne défait rien
    });
  }

  var api = {
    init: init,
    bulle: { montrer: bulleMontrer, cacher: bulleCacher },
    confirmer: confirmer,
    onOutilChange: function(fn){ ecouteursOutil.push(fn); },
    onReset: function(fn){ var bouton = el('pied-reset'); if (bouton) bouton.addEventListener('click', fn); },
    onAnnuler: function(fn){ var bouton = el('pied-annuler'); if (bouton) bouton.addEventListener('click', fn); }
  };
  return api;
})();
