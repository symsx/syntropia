# La strate couvre-sol, et son pouvoir couvrant noté — Martin Crawford

*Extraction en paraphrase du **chapitre 15** (p. 193-229) de « La Forêt-Jardin ». Versée le 2026-08-29.*

*Marquage `[Crawford]`. **Contexte : jardin-forêt tempéré**, non syntropie.*

> **C'est le chapitre que le dépôt réclamait depuis le 2026-08-28**, quand le point bloquant n° 1 est devenu actionnable : *« la base ne porte pas les critères qui permettraient de trouver — sur 80 candidats, `vitesse_croissance` et `est_couvre_sol` sont renseignées pour zéro. »*
>
> **Crawford note les deux, espèce par espèce.**
>
> **Manque la page 230** — une image pleine page. **La légende des symboles est arrivée le 2026-08-29**, pages 16-17 : voir `crawford-legende-des-notations.md`. Ce qui suit est décodé.

---

## 1. Ce que la strate est, et à quelles hauteurs

**L'introduction de l'ouvrage sépare la strate herbacée vivace et la strate couvre-sol.** Le chapitre 15 corrige aussitôt :

> **« Ce n'est pas toujours le cas. On peut installer une seule strate herbacée sous les arbustes — pas nécessairement basse, car certaines vivaces vigoureuses atteignent 1 m de hauteur — ou à certains endroits l'accompagner par des plantes plus petites encore. Très souvent néanmoins, on peut différencier deux, voire trois hauteurs distinctes dans cette strate. »**

**Et voici les hauteurs**, qu'aucune source versée ne donnait :

| Couche | Hauteur | Rôle |
|---|---|---|
| **Couche « de base »** | **Feuillage ≤ 30 cm** | *« Pour assurer une couverture optimale »* — elle tapisse le sol **sous les autres vivaces** |
| **Couche de base, variante haute** | **Environ 60 cm** | Permet d'installer de **grandes vivaces vigoureuses** au-dessus — grandes consoudes, raisin d'Amérique (*Phytolacca americana*) |
| **Vivaces vigoureuses** | **Jusqu'à 1 m** | La strate herbacée proprement dite |

> **Trente centimètres est le seuil qui manquait.** Le dépôt parlait de « couvre-sol ras » sans jamais le chiffrer. **Et la variante à 60 cm dit autre chose d'important** : la couche de base n'est pas *toujours* rase — elle est **plus basse que ce qui pousse au-dessus**, ce qui est une définition relationnelle, non absolue.

**La méthode que cela permet** : une **plantation en « îlots »** et d'autres techniques de polyculture, décrites au chapitre 16.

**Ce que le chapitre contient** : en majorité des **vivaces herbacées**, plus des **arbustes tapissants** signalés comme tels. **Les grimpantes sont au chapitre 17.**

---

## 2. Chaque espèce porte cinq notations — dont celle qui manquait

*Exemple, la gaulthérie rampante :*

```
GAULTHERIA PROCUMBENS — Gaulthérie rampante
Persistant | Zone 4 | Exposition : ☀
Tolérance à l'ombre : ●
Rendement : VV | Fertilité : AF
Pouvoir couvre-sol : V
Fleurs : blanches
```

| Champ | Ce qu'il vaut |
|---|---|
| **Feuillage** | Persistant · **semi-persistant** · caduc |
| **Zone** | Rusticité USDA |
| **Exposition** | Symbole |
| **Tolérance à l'ombre** | **Un nombre de pastilles** — une ou deux dans ce chapitre |
| **Rendement** | **V à VVVV** |
| **Fertilité** | **AF** autofertile · **AS** autostérile — et deux autres codes, voir ci-dessous |
| **Pouvoir couvre-sol** | **X · V · VV · VVV**, plus des intermédiaires comme **V-VV** |

**Et voici ce que les notes veulent dire** — légende p. 17 :

| Note | |
|---|---|
| **X** | Mauvais couvre-sol |
| **V** | Correct — **désherbage probable au printemps et en été** |
| **VV** | Bon — **désherbage occasionnel**, surtout au printemps |
| **VVV** | **Très bon. Empêche quasiment toutes les mauvaises herbes de pousser** |

| Pastille d'ombre | |
|---|---|
| ○ | Ne tolère pas l'ombre |
| ◐ | Ombre légère — 50 %, 4-5 h de soleil |
| ● | Ombre modérée — 20 %, 1-2 h de soleil direct |
| ● plein | Ombre importante — **pas de soleil direct** |
| **●●** | **L'ombre profonde sous les arbres et arbustes persistants** |

**Rendement** : V correct · VV bon · VVV très bon · **VVVV excellent** — et **ce n'est pas un rendement en kilos** : c'est la capacité à bien se comporter *dans une forêt comestible*.

**Fertilité** : **AF** autofertile · **PAF** partiellement · **AS** autostérile · **M/F** dioïque.

> **`Pouvoir couvre-sol` est exactement `est_couvre_sol`, et il est gradué — ce que la base ne prévoit pas.** Un booléen perdrait l'essentiel : la question n'est pas *« est-ce un couvre-sol ? »* mais **« couvre-t-il assez ? »**
>
> **Et `Rendement` n'est pas `vitesse_croissance`** — c'est la production. **La vigueur, elle, se lit dans le pouvoir couvrant et dans les notices** : *« s'étend largement »*, *« s'étale par drageons »*, *« habituellement par courants »*.

---

## 3. La distribution, sur 53 espèces notées

| Pouvoir couvre-sol | Espèces |
|---|---:|
| **V** — faible | **18** |
| **VV** — moyen | **31** |
| **VVV** — le maximum du chapitre | **4** |

> **Quatre seulement sur cinquante-trois atteignent le maximum.** C'est ce qui rend la notation utile : **elle discrimine.** Une colonne où tout le monde a la même valeur ne sert à rien.

### Les quatre

| Espèce | Feuillage | Zone | Rendement | Ombre | Taille |
|---|---|---|---|---|---|
| **Rubus tricolor** — ronce de Chine | **Persistant** | 6 | **VVVV** | **●●** | s'étend largement |
| **Rubus nepalensis** — ronce du Népal | **Persistant** | 7 | **VVVV** | ● | ~10 cm |
| **Symphytum spp.** — **consoudes** | Semi-persistant | 5 | **VVVV** | ● | jusqu'à 60 cm |
| **Vinca major & V. minor** — pervenches | **Persistant** | 4-7 | **VVVV** | **●●** | 30 à 60 cm, s'étend largement |

> **Les quatre cumulent le pouvoir couvrant maximal et le rendement maximal.** Ce n'est pas un arbitrage : **les meilleurs couvre-sol sont aussi les plus productifs.**
>
> **Et maintenant qu'on a la légende, cela se dit en clair** : ces quatre-là **empêchent quasiment toutes les mauvaises herbes de pousser**, et se comportent **excellemment** dans une forêt comestible. ***Rubus tricolor* et les pervenches y ajoutent la tolérance à l'ombre profonde sous les persistants** — le dernier cran de l'échelle, c'est-à-dire l'endroit d'un système où rien d'autre ne tient.

### Le profil complet, en clair

| | *Rubus tricolor* | *Vinca major* / *minor* |
|---|---|---|
| Empêche quasiment toute mauvaise herbe | **oui** | **oui** |
| Tolère l'ombre profonde sous persistants | **oui** | **oui** |
| Feuillage persistant | **oui** | **oui** |
| Se comporte excellemment en forêt comestible | **oui** | **oui** |
| Comestible | Fruits | — |

> **C'est le cahier des charges de la bordure bloque-herbe, rempli — moins le drainage.** Et le drainage n'est pas un champ de ces fiches : il entre par une autre porte, la correction de lumière — voir `crawford-legende-des-notations.md` § 3.

**Trois convergences, et elles ne sont pas cherchées :**

**La consoude arrive en tête** — ce que `[Robin]` fait en Bretagne depuis des années, sept tailles par an en bordure, et ce que le corpus syntropique plante en bordure extérieure à 1 pied / 40 cm (ch. 11). **Une troisième source, indépendante, la note au maximum.**

***Rubus tricolor* est le seul taxon de l'annexe 2 portant la mention « couvre-sol »** — et c'est ici l'un des quatre VVV. **Deux tables du même livre, même conclusion.**

**Et deux des quatre — *Rubus tricolor* et les pervenches — portent deux pastilles d'ombre**, la valeur la plus haute du chapitre. **C'est le second critère bloquant du dépôt** : tenir à l'ombre.

---

## 4. Le piétinement — un critère que le dépôt n'avait pas

> **« Ces plantes seront probablement sujettes à un piétinement occasionnel. Chaque espèce a sa propre tolérance, et très peu aiment être continuellement piétinées. Essayez de prendre une route différente à chaque passage, afin que les mêmes plantes ne soient pas écrasées à chaque fois. »**

**Trois niveaux apparaissent dans les notices**, en toutes lettres :

| Niveau | Formulation du livre |
|---|---|
| **Fort** | *« Tolère le piétinement »* |
| **Léger** | *« Tolère un léger piétinement »* |
| **Occasionnel** | *« Tolère un piétinement occasionnel »* |

> **C'est un critère requêtable que ni le corpus syntropique ni `plantae-corpus` ne portent**, et il décide de deux choses : **ce qu'on peut planter dans un passe-pied**, et **où passe le chemin**.
>
> **Et la consigne qui va avec est une règle de conduite, non de plante** : *prendre une route différente à chaque passage*. Le dépôt pose que *« la pénibilité est un symptôme de conception »* — voici l'inverse, une conception qui répartit l'usure.

---

## 5. Deux exigences, et deux règles de composition

### Ce qu'un couvre-sol doit être

> **« En plus d'être basses, les plantes couvre-sol doivent tolérer l'ombre et, de préférence, encore produire dans ces conditions. »**

**Deux conditions, pas une.** Tolérer l'ombre ne suffit pas : **produire à l'ombre** est la préférence. C'est plus exigeant que le cahier des charges du dépôt, qui demandait seulement de bloquer l'herbe.

### Mélanger les profondeurs racinaires

> **« Je donne une indication sur la structure racinaire des plantes couvre-sol recommandées. Gardez ce point à l'esprit lorsque vous installez autre chose entre elles, et essayez de mélanger des plantes à système racinaire profond et plus superficiel, afin d'utiliser le volume de sol disponible avec plus d'efficacité. »**

> **C'est la septième famille des critères de guilde `[Plantae]`** — *« on évite de mettre deux espèces à enracinement identique en concurrence directe »* — appliquée à une strate entière, et **la structure racinaire est publiée espèce par espèce**.

### Ce qu'on fait d'un « mauvais couvre-sol »

> **« Une plante qualifiée de "mauvais couvre-sol" dans ce chapitre doit être cultivée en association avec une espèce plus basse qui couvrira le terrain plus efficacement et permettra de réduire le désherbage. »**

> **Un mauvais couvre-sol n'est pas écarté : il est appareillé.** C'est la même logique que la couche de base sous les grandes vivaces — **on ne choisit pas une plante, on choisit un empilement.**

---

## 6. Ce que cela change pour le point bloquant n° 1

| Critère du cahier des charges | Avant le 2026-08-29 | Maintenant |
|---|---|---|
| **Moins de 3,50 m** | 7 candidats issus de `guilde.csv` | **Sans objet ici** : la strate se joue **sous 1 m**, et la couche de base **sous 30 cm** |
| **Bloque l'herbe** | Aucun critère dans la base | **`Pouvoir couvre-sol`, gradué V / VV / VVV** — et **4 espèces au maximum sur 53** |
| **Tolère l'ombre** | **Le point resté ouvert** | **Noté par pastilles**, et deux des quatre meilleurs portent la valeur haute |
| **Ce que la plante héberge** `[Gotheron]` | Partiel | Les notices portent *« mellifère »*, et l'annexe 3 le calendrier |
| **Tolère un sol peu drainant** | **Le point resté ouvert** | ⚠️ **Pas un champ** — mais il entre **par la lumière** : *« les plantes de sous-bois toléreront davantage de soleil dans un sol humide que dans un sol sec »* (p. 17). **Sur sol drainant, elles en tolèrent moins** — la correction est à appliquer aux deux échelles |

> **Le point bloquant n° 1 se referme sur trois de ses cinq critères.** Ce qui reste : **le drainage**, qui n'est pas un champ du livre, et le fait que **cette liste est celle d'un jardin-forêt du Devon** — 53 espèces, à croiser avec ce qui pousse en Haute-Vienne.
>
> **Et le chemin nommé le 2026-08-28 tient** : remplir `est_couvre_sol` et `vitesse_croissance`. **La source existe maintenant, avec une échelle à trois crans plutôt qu'un booléen** — c'est une remontée pour `plantae-corpus`.

---

## 7. Ce qui n'est pas versé, et pourquoi

**Les 53 notices complètes** — taille, culture, floraison, fructification, utilisations, récolte, cuisson, usages secondaires, multiplication, entretien. **Leur place est la base d'espèces**, non une fiche de corpus.

**Ce qu'il faudrait en extraire, dans l'ordre** :

| Champ | État dans `plantae-corpus` |
|---|---|
| `pouvoir_couvre_sol` — **échelle à trois crans** | À créer. `est_couvre_sol` est un booléen : **il perd l'information** |
| `tolerance_ombre` | Existe sous forme d'ensoleillement ; **la graduation de Crawford est plus fine** |
| `tolerance_pietinement` | **À créer** — trois niveaux, en toutes lettres dans les notices |
| `structure_racinaire` | Existe — `tendance_racinaire`. **À alimenter depuis ce chapitre** |

**La légende des symboles est arrivée** le 2026-08-29 — pages 16-17, `crawford-legende-des-notations.md`.

**Et deux champs de plus s'y révèlent** : l'**exposition préférée**, distincte de la tolérance, et la **fertilité** en quatre codes, dont **M/F** qui outille enfin la clé sexuelle de `[Nathié]`.
