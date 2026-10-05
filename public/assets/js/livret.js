/* Livret — pagine un chapitre trop long pour tenir sans défilement.
 *
 * Les chapitres de documentation-site.php/documentation-saf.php sont de
 * la prose libre, écrite par le porteur : sa longueur n'est pas bornée,
 * et « aucun défilement, nulle part » l'est. Le découpage ENTRE
 * chapitres reste en CSS pur (:has() + label[for], voir la page elle-
 * même) — ce fichier ajoute un second niveau, À L'INTÉRIEUR d'un
 * chapitre trop long pour l'écran : une pagination mesurée en pixels,
 * pas un compte de paragraphes, pour rester juste quel que soit
 * l'écran. Étend la décision déjà prise pour l'import GeoJSON de la
 * carte : « le bureau suppose déjà du JS partout » — cette page n'était
 * JS-libre que tant qu'un chapitre tenait toujours dans l'écran, ce qui
 * n'est plus garanti dès qu'un livret grandit. Remarque du porteur,
 * 2026-09-15, capture d'écran sur téléphone à l'appui.
 */
(function () {
  'use strict';

  function defaire(article) {
    var conteneur = article.querySelector('.doc-sous-pages');
    if (conteneur) {
      var apres = conteneur;
      Array.prototype.slice.call(conteneur.querySelectorAll('.doc-sous-page')).forEach(function (bloc) {
        Array.prototype.slice.call(bloc.children).forEach(function (p) {
          article.insertBefore(p, apres);
        });
      });
      conteneur.remove();
    }
    var nav = article.querySelector('.doc-sous-nav');
    if (nav) { nav.remove(); }
  }

  function budgetDisponible(article, page, bureau, retenue) {
    var styleP = getComputedStyle(page);
    var budget = bureau.clientHeight - parseFloat(styleP.paddingTop) - parseFloat(styleP.paddingBottom);

    var repere = article.querySelector('.doc-chapitre-repere');
    var h2 = article.querySelector('h2');
    if (repere) { budget -= repere.offsetHeight + parseFloat(getComputedStyle(repere).marginBottom); }
    if (h2) { budget -= h2.offsetHeight + parseFloat(getComputedStyle(h2).marginBottom); }

    var nav = page.querySelector('.doc-page-nav[data-pour="' + article.dataset.chapitre + '"]');
    if (nav) {
      var afficheAvant = nav.style.display;
      nav.style.display = 'flex';
      budget -= nav.offsetHeight + parseFloat(getComputedStyle(nav).marginTop);
      nav.style.display = afficheAvant;
    }

    // Réservé pour la pagination interne elle-même — avant de savoir si
    // elle sera nécessaire. Mieux vaut paginer un peu tôt que déborder.
    budget -= 40;
    // Ce que la mesure a trouvé en trop, tour d'avant (voir paginerTout).
    return budget - (retenue || 0);
  }

  function paginerChapitre(article, retenue) {
    defaire(article);
    article.querySelectorAll(':scope > p').forEach(function (p) { p.style.fontSize = ''; });

    var page = article.closest('.doc-page');
    var bureau = document.getElementById('bureau');
    if (!page || !bureau) { return; }

    var paragraphes = Array.prototype.slice.call(article.querySelectorAll(':scope > p:not(.doc-chapitre-repere)'));
    if (paragraphes.length === 0) { return; }

    var disponible = budgetDisponible(article, page, bureau, retenue);
    // La hauteur se compte depuis le haut de la sous-page EN COURS : comptée depuis le premier paragraphe du
    // chapitre, chaque paragraphe après la première sous-page en ouvrait une nouvelle (« Concevoir : les bordures »,
    // 20 paragraphes : 14 sous-pages à 776×977, presque vides ; corrigé le 2026-10-05).
    var debut = paragraphes[0].offsetTop;
    var groupes = [[]];
    paragraphes.forEach(function (p) {
      var bas = (p.offsetTop - debut) + p.offsetHeight;
      var groupeCourant = groupes[groupes.length - 1];
      if (groupeCourant.length > 0 && bas > disponible) {
        groupes.push([p]);
        debut = p.offsetTop;
      } else {
        groupeCourant.push(p);
      }
    });

    if (groupes.length <= 1) { return; }

    var conteneur = document.createElement('div');
    conteneur.className = 'doc-sous-pages';
    groupes.forEach(function (groupe, i) {
      var bloc = document.createElement('div');
      bloc.className = 'doc-sous-page';
      bloc.hidden = i !== 0;
      groupe.forEach(function (p) { bloc.appendChild(p); });
      conteneur.appendChild(bloc);
    });
    article.appendChild(conteneur);

    var nav = document.createElement('div');
    nav.className = 'doc-sous-nav';
    var precedent = document.createElement('button');
    precedent.type = 'button'; precedent.className = 'doc-sous-bouton'; precedent.textContent = '◂';
    precedent.setAttribute('aria-label', 'Section précédente de ce chapitre');
    var compte = document.createElement('span');
    compte.className = 'doc-sous-compte';
    var suivant = document.createElement('button');
    suivant.type = 'button'; suivant.className = 'doc-sous-bouton'; suivant.textContent = '▸';
    suivant.setAttribute('aria-label', 'Section suivante de ce chapitre');
    nav.append(precedent, compte, suivant);
    article.appendChild(nav);

    var courant = 0;
    function montrer(i) {
      courant = Math.max(0, Math.min(groupes.length - 1, i));
      Array.prototype.slice.call(conteneur.querySelectorAll('.doc-sous-page')).forEach(function (b, j) {
        b.hidden = j !== courant;
      });
      compte.textContent = (courant + 1) + ' / ' + groupes.length;
      precedent.disabled = courant === 0;
    }
    precedent.addEventListener('click', function () { montrer(courant - 1); });
    suivant.addEventListener('click', function () {
      if (courant < groupes.length - 1) { montrer(courant + 1); return; }
      // Dernière section : le bouton du bas fait passer au chapitre suivant.
      var boutonChapitreSuivant = page.querySelector(
        '.doc-page-nav[data-pour="' + article.dataset.chapitre + '"] .doc-tourner-suivant');
      if (boutonChapitreSuivant) { boutonChapitreSuivant.click(); }
    });
    montrer(0);
  }

  /* De combien la page dépasse-t-elle le bureau, pour CE chapitre ? Le budget est
   * une estimation : il ignore les marges du cadre et, sur téléphone, la hauteur
   * de la rangée de chapitres posée au-dessus — qui grandit avec le nombre de
   * chapitres (9 chapitres : la page débordait de 123 px). On mesure donc, sous-
   * page par sous-page. Les chapitres sont empilés dans la même case : la page
   * prend la hauteur du plus haut. Les autres sont donc sortis du flux le temps
   * de la mesure — sinon un seul chapitre difficile faisait doubler la retenue de
   * tous, et « Concevoir : les bordures » tombait à un paragraphe par sous-page
   * (14 sous-pages pour 20 paragraphes à 776×977 ; corrigé le 2026-10-05). */
  function depassement(article) {
    var page = article.closest('.doc-page');
    var bureau = document.getElementById('bureau');
    if (!page || !bureau) { return 0; }
    var limite = bureau.getBoundingClientRect().bottom;
    var autres = Array.prototype.slice.call(page.querySelectorAll('.doc-chapitre')).filter(function (a) { return a !== article; });
    autres.forEach(function (a) { a.style.display = 'none'; });
    var pire = page.getBoundingClientRect().bottom - limite;
    var blocs = Array.prototype.slice.call(article.querySelectorAll('.doc-sous-page'));
    var etats = blocs.map(function (b) { return b.hidden; });
    blocs.forEach(function (voulu) {
      blocs.forEach(function (b) { b.hidden = b !== voulu; });
      pire = Math.max(pire, page.getBoundingClientRect().bottom - limite);
    });
    blocs.forEach(function (b, i) { b.hidden = etats[i]; });
    autres.forEach(function (a) { a.style.display = ''; });
    return Math.ceil(pire);
  }

  /* Un paragraphe seul plus haut que la page ne se coupe pas (on pagine entre deux
   * paragraphes, et le texte du livret SAF est au porteur : 633 caractères d'un
   * tenant, 39 px de trop à 320×568). Sa police se réduit alors, par petits pas,
   * jusqu'à ce qu'il tienne — un contenu trop long se réduit. */
  function reduireTropLongs(article) {
    var page = article.closest('.doc-page');
    var bureau = document.getElementById('bureau');
    if (!page || !bureau) { return; }
    // Seule la sous-page affichée compte, les autres chapitres sortis du flux (voir depassement).
    var autres = Array.prototype.slice.call(page.querySelectorAll('.doc-chapitre')).filter(function (a) { return a !== article; });
    autres.forEach(function (a) { a.style.display = 'none'; });
    var deborde = function () { return page.getBoundingClientRect().bottom - bureau.getBoundingClientRect().bottom > 0; };
    var blocs = Array.prototype.slice.call(article.querySelectorAll('.doc-sous-page'));
    var etats = blocs.map(function (b) { return b.hidden; });
    (blocs.length ? blocs : [article]).forEach(function (bloc) {
      blocs.forEach(function (b) { b.hidden = b !== bloc; });
      var ps = Array.prototype.slice.call(bloc.querySelectorAll(bloc === article ? ':scope > p:not(.doc-chapitre-repere)' : ':scope > p'));
      if (ps.length !== 1) { return; }
      var taille = parseFloat(getComputedStyle(ps[0]).fontSize);
      for (var pas = 0; pas < 6 && deborde(); pas++) {
        taille -= 0.5;
        ps[0].style.fontSize = taille + 'px';
      }
    });
    blocs.forEach(function (b, i) { b.hidden = etats[i]; });
    autres.forEach(function (a) { a.style.display = ''; });
  }

  function paginerTout() {
    document.querySelectorAll('.doc-chapitre').forEach(function (a) {
      var retenue = 0;
      for (var tour = 0; tour < 8; tour++) {
        paginerChapitre(a, retenue);
        var trop = depassement(a);
        if (trop <= 0) { break; }
        // On coupe entre deux paragraphes : retrancher 13 px ne fait rien tant que le
        // dernier paragraphe garde de la marge. La retenue double donc à chaque tour.
        retenue += Math.max(trop, retenue);
      }
      reduireTropLongs(a);
    });
  }

  /* La liste des chapitres, elle aussi, doit tenir dans le bureau. Elle
   * grandit avec le livret (« L'atelier » : 22 chapitres le 2026-10-05,
   * 1 028 px pour 879 de bureau à 776×977 — la colonne, centrée, montait
   * sur le bandeau). D'abord elle se resserre (`doc-chapitres-serre`) ;
   * si cela ne suffit pas, elle se découpe en pages de chapitres, avec la
   * même petite navigation que les sections d'un chapitre, et montre celle
   * du chapitre ouvert. Remarque du porteur, 2026-10-05, capture à l'appui. */
  var listePages = null;

  function montrerPageDeListe(i) {
    if (!listePages) { return; }
    listePages.courant = Math.max(0, Math.min(listePages.groupes.length - 1, i));
    listePages.groupes.forEach(function (groupe, j) {
      groupe.forEach(function (li) { li.hidden = j !== listePages.courant; });
    });
    listePages.compte.textContent = (listePages.courant + 1) + ' / ' + listePages.groupes.length;
    listePages.precedent.disabled = listePages.courant === 0;
    listePages.suivant.disabled = listePages.courant === listePages.groupes.length - 1;
  }

  function montrerChapitreOuvertDansListe() {
    if (!listePages) { return; }
    listePages.groupes.forEach(function (groupe, j) {
      groupe.forEach(function (li) {
        if (li.querySelector('input:checked')) { montrerPageDeListe(j); }
      });
    });
  }

  function ajusterListe() {
    var bloc = document.querySelector('.doc-chapitres-bloc');
    var liste = bloc && bloc.querySelector('.doc-chapitres');
    var bureau = document.getElementById('bureau');
    var corps = document.querySelector('.doc-corps');
    if (!liste || !bureau || !corps) { return; }

    // Repartir de la liste entière, non resserrée.
    var ancienne = bloc.querySelector('.doc-liste-nav');
    if (ancienne) { ancienne.remove(); }
    listePages = null;
    var items = Array.prototype.slice.call(liste.children);
    items.forEach(function (li) { li.hidden = false; });
    bloc.classList.remove('doc-chapitres-serre');
    if (getComputedStyle(bloc).display === 'none') { return; } // téléphone : la liste est masquée

    var sb = getComputedStyle(bureau), sc = getComputedStyle(corps);
    var disponible = bureau.clientHeight - parseFloat(sb.paddingTop) - parseFloat(sb.paddingBottom)
      - parseFloat(sc.paddingTop) - parseFloat(sc.paddingBottom);
    if (bloc.offsetHeight <= disponible) { return; }

    bloc.classList.add('doc-chapitres-serre');
    if (bloc.offsetHeight <= disponible) { return; }

    // Toujours trop haute : des pages de chapitres. La navigation prend sa place d'abord.
    var nav = document.createElement('div');
    nav.className = 'doc-sous-nav doc-liste-nav';
    var precedent = document.createElement('button');
    precedent.type = 'button'; precedent.className = 'doc-sous-bouton'; precedent.textContent = '◂';
    precedent.setAttribute('aria-label', 'Chapitres précédents');
    var compte = document.createElement('span');
    compte.className = 'doc-sous-compte';
    var suivant = document.createElement('button');
    suivant.type = 'button'; suivant.className = 'doc-sous-bouton'; suivant.textContent = '▸';
    suivant.setAttribute('aria-label', 'Chapitres suivants');
    nav.append(precedent, compte, suivant);
    bloc.appendChild(nav);

    var place = disponible - (bloc.offsetHeight - liste.offsetHeight);
    var groupes = [[]];
    var debut = items.length ? items[0].offsetTop : 0; // offsetTop se compte depuis le bureau, pas depuis la liste
    items.forEach(function (li) {
      var bas = li.offsetTop + li.offsetHeight - debut;
      var courant = groupes[groupes.length - 1];
      if (courant.length > 0 && bas > place) {
        groupes.push([li]);
        debut = li.offsetTop;
      } else {
        courant.push(li);
      }
    });

    listePages = { groupes: groupes, compte: compte, precedent: precedent, suivant: suivant, courant: 0 };
    precedent.addEventListener('click', function () { montrerPageDeListe(listePages.courant - 1); });
    suivant.addEventListener('click', function () { montrerPageDeListe(listePages.courant + 1); });
    montrerChapitreOuvertDansListe();
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.doc-corps')) { return; }
    ajusterListe();
    paginerTout();
    document.querySelectorAll('.doc-chapitres input[type=radio]').forEach(function (bouton) {
      bouton.addEventListener('change', function () {
        montrerChapitreOuvertDansListe(); // tourné par les boutons du bas de la page
        paginerTout();
      });
    });
    var redim;
    window.addEventListener('resize', function () {
      clearTimeout(redim);
      redim = setTimeout(function () { ajusterListe(); paginerTout(); }, 150);
    });
  });
})();
