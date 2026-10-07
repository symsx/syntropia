/* Le manifeste des étapes — reprend plan-du-site.md, un seul endroit.
   Consulté par gabarit.js pour construire « Phase projet » et la
   navigation étape précédente / étape suivante.

   `pret` dit si la page existe déjà (`<id>.php` dans public/) — sans
   lui, un « suivant » cliquable mènerait à une page qui n'existe pas
   encore. Absent ou faux = pas construite ; le bouton se grise, montré
   comme tout « à venir » du site, jamais un lien mort. */
(function(){
  'use strict';

  window.PHASES = [
    { id: 'definition',  numero: 1, libelle: 'Définition' },
    { id: 'conception',  numero: 2, libelle: 'Conception' },
    { id: 'realisation', numero: 3, libelle: 'Réalisation' },
    { id: 'suivi',       numero: 4, libelle: 'Suivi' }
  ];

  window.ETAPES = [
    { id: 'definition-vision',     phase: 'definition', titre: 'Vision', pret: true },
    { id: 'definition-objectifs',  phase: 'definition', titre: 'Objectifs', pret: true },
    { id: 'definition-moyens',     phase: 'definition', titre: 'Moyens', pret: true },
    { id: 'definition-site',       phase: 'definition', titre: 'Site', pret: true },

    { id: 'conception-etude-site',      phase: 'conception', titre: 'Étude du site', pret: true },
    { id: 'conception-etude-sol',       phase: 'conception', titre: 'Étude du sol', pret: true },
    { id: 'conception-palette',         phase: 'conception', titre: 'La palette', pret: true },
    { id: 'conception-parcelles',       phase: 'conception', titre: 'Créer les parcelles', pret: true },
    { id: 'conception-scenario',        phase: 'conception', titre: 'Scénario de plantation', pret: true },
    { id: 'conception-materiel-et-eau', phase: 'conception', titre: 'Matériel et eau', pret: true },
    { id: 'conception-calendrier',      phase: 'conception', titre: 'Calendrier', pret: true },

    { id: 'realisation-preparation-parcelle', phase: 'realisation', titre: 'Préparation de la parcelle', pret: true },
    { id: 'realisation-materiel-vegetal',     phase: 'realisation', titre: 'Matériel végétal', pret: true },
    { id: 'realisation-outils-et-plans',      phase: 'realisation', titre: 'Outils et plans', pret: true },
    { id: 'realisation-consignation',         phase: 'realisation', titre: 'Consignation', pret: true },

    { id: 'suivi-arrosage-germination', phase: 'suivi', titre: 'Arrosage et germination', pret: true },
    { id: 'suivi-perturbation',         phase: 'suivi', titre: 'Perturbation', pret: true },
    { id: 'suivi-saut-du-chat',         phase: 'suivi', titre: 'Saut du chat', pret: true },
    { id: 'suivi-consignation',         phase: 'suivi', titre: 'Consignation', pret: true }
  ];
})();
