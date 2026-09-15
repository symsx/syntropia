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

  function budgetDisponible(article, page, bureau) {
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
    return budget;
  }

  function paginerChapitre(article) {
    defaire(article);

    var page = article.closest('.doc-page');
    var bureau = document.getElementById('bureau');
    if (!page || !bureau) { return; }

    var paragraphes = Array.prototype.slice.call(article.querySelectorAll(':scope > p:not(.doc-chapitre-repere)'));
    if (paragraphes.length === 0) { return; }

    var disponible = budgetDisponible(article, page, bureau);
    var premierTop = paragraphes[0].offsetTop;
    var groupes = [[]];
    paragraphes.forEach(function (p) {
      var bas = (p.offsetTop - premierTop) + p.offsetHeight;
      var groupeCourant = groupes[groupes.length - 1];
      if (groupeCourant.length > 0 && bas > disponible) {
        groupes.push([p]);
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

  function paginerTout() {
    document.querySelectorAll('.doc-chapitre').forEach(paginerChapitre);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.doc-corps')) { return; }
    paginerTout();
    document.querySelectorAll('.doc-chapitres input[type=radio]').forEach(function (bouton) {
      bouton.addEventListener('change', paginerTout);
    });
    var redim;
    window.addEventListener('resize', function () {
      clearTimeout(redim);
      redim = setTimeout(paginerTout, 150);
    });
  });
})();
