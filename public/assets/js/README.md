# js/

`gabarit.js` — la coquille commune (thème, tiroirs, Échap, menu
d'outils, bulle), pilotée par `Gabarit.init({ site, etape, contexte })`.
`etapes.js` — le manifeste des 4 phases et de leurs étapes, repris de
`plan-du-site.md`. `carte.js` — le moteur de tuiles sans dépendance
(Web Mercator, OpenStreetMap), partagé par toute page qui pose un point
sur une carte (`data-carte`) ; porté de `application/public/assets/carte.js`
(dépôt de référence), voir architecture-technique.md § carte interactive
du terrain. Le script propre à une étape (ex. `conception-parcelles.js`)
reste à côté de sa page, sous `public/`. Voir `architecture-technique.md`.
