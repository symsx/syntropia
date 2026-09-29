/* La strate proposée d'après le référentiel — même règle que Strates::proposee() côté serveur (Théry, ch. 05) : la strate
   de production ou la strate de service (biomasse, bloque-herbe) que dit Espèces ; à défaut, la seule strate de l'espèce ;
   plusieurs sans usage dit, rien n'est deviné. La strate n'est remplie que si la personne ne l'a pas choisie elle-même ;
   une note dit toujours ce que le référentiel en sait. Utilisé par conception-scenario.php et conception-potager.php. */
(function(){
  'use strict';
  window.StrateProposee = function(o){
    // o : { espece, strate, note : éléments ; especes : { nom en minuscules → { p, s, toutes } } ;
    //       libelles : { clé → libellé } ; production : function() → bool ; autres : éléments dont le changement relance }
    if (!o.espece || !o.strate || !o.note) return;
    var auto = null;   // la dernière valeur posée par la proposition, pour ne jamais écraser un choix de la personne
    function donnees(){
      var nom = o.espece.value.trim().toLowerCase();
      return o.especes[nom] || (nom.slice(-1) === 's' ? o.especes[nom.slice(0, -1)] : null) || null;
    }
    function lib(k){ return (o.libelles[k] || k).toLowerCase(); }
    function maj(){
      var d = donnees();
      if (!d){ o.note.hidden = true; return; }
      var prod = o.production();
      var dite = prod ? d.p : d.s;
      var proposee = dite || (d.toutes.length === 1 ? d.toutes[0] : null);
      if (proposee && (o.strate.value === '' || o.strate.value === auto)){ o.strate.value = proposee; auto = proposee; }
      var usages = [];
      if (d.p) usages.push('production en ' + lib(d.p));
      if (d.s) usages.push('service en ' + lib(d.s));
      var texte = usages.length
        ? 'Le référentiel dit : ' + usages.join(', ') + ' (la strate dépend de l’usage — Théry, ch. 05).'
        : 'Le référentiel range cette plante en strate ' + d.toutes.map(lib).join(' ou ') + '.';
      if (proposee) texte += ' Proposée ici : ' + lib(proposee) + (prod ? ', pour la production.' : ', pour le service.');
      else texte += ' Pour ' + (prod ? 'la production' : 'le service') + ', il ne dit pas laquelle : à choisir, et à noter dans Espèces.';
      if (o.strate.value && o.strate.value !== proposee) texte += ' Vous avez choisi ' + lib(o.strate.value) + '.';
      o.note.textContent = texte;
      o.note.hidden = false;
    }
    o.espece.addEventListener('change', maj);
    o.espece.addEventListener('input', maj);
    o.strate.addEventListener('change', function(){ if (o.strate.value !== auto) auto = null; maj(); });
    (o.autres || []).forEach(function(el){ el.addEventListener('change', maj); });
    maj();
  };
})();
