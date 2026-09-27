# Passation — emaee.fr

Document de reprise. À lire en entier avant de toucher au code : il contient
plusieurs pièges qui ont déjà coûté du temps.

Dernière mise à jour : 27 septembre 2026.

---

## 1. Le site en une page

Site vitrine et back-office d'une entreprise multitechnique (électricité,
plomberie, chauffage, climatisation, VMC, portail/visiophone, automatismes).

| | |
|---|---|
| **Langage** | PHP 8.4, `declare(strict_types=1)`, **aucun framework** |
| **Base** | MySQL, accès par PDO (`includes/db.php`) |
| **Hébergement** | o2switch, dossier `/public_html/` |
| **Déploiement** | automatique par FTP à chaque `push` sur `main` (`.github/workflows/deploy.yml`) |
| **Langue** | tout est en français : interface, messages, commentaires, noms de variables métier |

### Attention au déploiement

Un `push` sur `main` **met le site en production immédiatement**. Il n'y a
pas d'étape de validation. Conséquences :

- Toujours faire tourner `bash tests/run.sh` avant de pousser.
- Ne jamais pousser un fichier à moitié fini.
- Les fichiers qui vivent sur le serveur et ne doivent pas être écrasés sont
  listés dans `exclude:` du workflow : `config/config.local.php`,
  `storage/uploads/`, `storage/logs/`, les marqueurs `storage/.mig_*`, plus
  `tests/` et ce document.

---

## 2. Arborescence

```
index.php              Tout le site public : routage + gabarits de chaque page
includes/              Le moteur — deux familles, voir ci-dessous
  ── Site vitrine et back-office ──
  bootstrap.php          Amorçage + migrations automatiques
  helpers.php            ~2000 lignes : réglages, zones, société, routage
  zones_core.php         Source unique des zones d'intervention
  zone_vars.php          Variables {ville} {region} {departement}…
  admin_fields.php       Catalogue des textes modifiables
  admin_replace.php      Chercher / remplacer
  admin_team.php         Comptes admin, droits par écran, journal
  inline_edit.php        Modification directement sur la page
  stats.php              Compteurs de visites et de clics
  zone_content.php       Textes pré-rédigés par zone
  render.php             En-tête et pied de page du site public
  db.php auth.php schema.sql pdf_generator.php
  ── Circuit d'intervention automatisé (voir §4bis) ──
  workflow.php qualification.php assignment.php review.php
  pricing.php price_grid_seed.php materials_catalog.php
  invoicing.php pennylane.php yousign.php client360.php
  integrations.php notifications.php claude.php simple_pdf.php
admin/                 Back-office (une page = un fichier)
tech/                  Espace technicien (application installable, hors ligne)
dispatcher/            Espace dispatcher
cron/                  Tâches planifiées (synchronisation Pennylane)
docs/AUTOMATISATION.md Mise en service du circuit automatisé
assets/css|js          style.css (site) et admin.css (back-office)
storage/               Téléversements, journaux, marqueurs de migration
tests/                 Suite de tests — voir §6
api/                   ⚠️ Dossier mort, voir §8
```

`cron/` et `docs/` sont protégés par un `.htaccess` en `Require all denied` :
ils ne doivent jamais être servis par le web.

---

## 3. Les cinq mécanismes à comprendre

Tout le reste en découle. Les comprendre évite de réécrire ce qui existe.

### 3.1 Héritage par préfixe de clé — le cœur du système

Un réglage se lit avec `setting('ma_cle', 'valeur par défaut')`. La fonction
cherche dans cet ordre :

1. `z:{zone}:ma_cle` — la version propre à la zone en cours
2. `ma_cle` — la version globale
3. la valeur par défaut écrite dans le code

**Une valeur vide dans une zone signifie « hérite du global ».** C'est
volontaire : on ne saisit que les différences.

Ce seul mécanisme fait tourner trois fonctionnalités : les zones
géographiques, les variables de lieu (`zvar_ville`, `zvar_region`…) et le
catalogue de contenu. Ne pas en ajouter un quatrième à côté.

### 3.2 Le catalogue — une seule liste de référence

`admin_page_catalog()` dans `includes/admin_fields.php` décrit chaque texte
modifiable du site : sa clé, son libellé en français, son type, sa valeur par
défaut, son aide. Cette liste alimente **à elle seule** :

- les écrans de modification (`admin/page_content.php`)
- la recherche et le remplacement (`admin/search.php`)
- la modification directe sur la page
- la conversion en variables

Ajouter un texte modifiable = l'ajouter au catalogue. Rien d'autre.

### 3.3 Modification directe sur la page

Quand un admin connecté ouvre une page du site avec `?admin_edit=1`,
`setting()` enrobe chaque valeur de marqueurs invisibles (`\x02clé\x03valeur\x04`).
Ces marqueurs survivent à `htmlspecialchars()`. Un `ob_start()` posé dans
`bootstrap.php` les transforme en zones éditables — y compris quand la page
se termine par `exit`.

Un champ dont la valeur contient `{` est exclu : on ne modifie pas un texte
à variables directement sur la page, sinon on écraserait la variable par sa
valeur résolue.

### 3.4 Migrations automatiques

Il n'y a pas d'outil de migration. Le schéma évolue dans `bootstrap.php`,
chaque bloc gardé par un fichier témoin dans `storage/` :

```php
$_mf17 = __DIR__.'/../storage/.mig_v15_zones_unique';
if (!file_exists($_mf17)) {
    foreach ([...]) { try { db_execute($__sql); } catch (Throwable $_me) {} }
    @file_put_contents($_mf17, date('c'));
}
```

Règles : toujours dans un `try/catch` silencieux (un `ALTER` déjà appliqué ne
doit pas casser le site), toujours idempotent, toujours un nouveau numéro.
Les témoins sont exclus du déploiement, donc chaque migration s'exécute une
fois sur le serveur, à la première visite après la mise en ligne.

### 3.5 Droits par écran

`admin_screens()` (`includes/admin_team.php`) est le registre du menu.
`require_admin()` vérifie l'accès tout seul : une nouvelle page d'admin est
protégée dès qu'elle est déclarée dans le registre.

`admin_hidden_screens()` liste les écrans retirés du menu mais toujours
actifs. Chacun est rattaché à un écran parent **qui décide de ses droits** :
ranger le menu ne retire donc jamais un accès à un compte secondaire.

⚠️ **Un fichier d'admin non déclaré est inaccessible aux comptes non
principaux.** `test_menu.php` vérifie qu'aucun fichier n'est oublié.

---

## 4. La source unique des zones

C'est le chantier le plus récent et le plus structurant.

**Le problème d'origine.** La même information était saisie à quatre endroits
sans lien entre eux : la table `zones` pour les sous-sites, le réglage
`zones_page_settings` pour la page Nos zones, `home_zone_cards` pour le bas
des pages Service, `contact_zone_tags` pour la page Contact. Les villes de
Paris étaient écrites quatre fois. Modifier l'une n'en changeait aucune autre.

**La règle maintenant.** La table `zones` est la seule référence.
`intervention_zones()` (`includes/zones_core.php`) la lit, et les quatre
emplacements du site en découlent.

```
table zones ─┬─► accueil, section Zones
             ├─► page Nos zones      (zones_page_settings()['regions'])
             ├─► bas des pages Service
             └─► page Contact        (intervention_cities())
```

Un seul écran l'alimente : `admin/zones.php`.

**À ne pas faire :** réintroduire un réglage qui duplique ces données. Si un
nouvel emplacement doit afficher des zones, il appelle `intervention_zones()`.

Colonnes de présentation ajoutées à la table : `depts`, `delay`, `color`,
`intro`. `delay` est entre accents graves dans les requêtes, par prudence.

`test_zones_unique.php` verrouille ce comportement : il change une ville et
vérifie qu'elle change bien aux quatre endroits.

---

## 4bis. Le circuit d'intervention automatisé

**Ce sous-système a été construit par d'autres sessions, pas par celle qui
rédige ce document.** Ce qui suit décrit son périmètre et où le trouver ; il
ne remplace pas une lecture du code avant d'y toucher.

Il couvre la vie d'une intervention, de l'appel à la facture encaissée :

| Étape | Fichier principal |
|---|---|
| Grille tarifaire et TVA | `includes/pricing.php`, `price_grid_seed.php` |
| Qualification de l'appel assistée par Claude | `includes/qualification.php`, `claude.php` |
| Assignation, technicien suggéré | `includes/assignment.php` |
| Rapport du technicien, chiffré par la grille | `tech/`, `includes/review.php` |
| Relecture et brouillon de facture | `includes/review.php`, `invoicing.php` |
| Comptabilité Pennylane | `includes/pennylane.php`, `cron/pennylane_sync.php` |
| Devis signés à distance (Yousign) | `includes/yousign.php` |
| Fiche client 360°, mauvais payeurs | `includes/client360.php` |
| Enchaînement des statuts | `includes/workflow.php` |

**Sa mise en service est documentée à part**, dans
`docs/AUTOMATISATION.md` : clés d'API à saisir, tâche cron o2switch toutes
les 15 minutes, webhook Yousign, vérifications avant production, journaux.
Ne pas dupliquer ces instructions ici — les tenir à jour là-bas.

Points d'attention : ce circuit appelle des services externes payants
(Claude, Pennylane, Yousign) et manipule des données clients et des
montants. La suite de tests de ce dépôt ne le couvre pas.

---

## 5. Conventions de travail

Elles viennent du propriétaire du site et ont été confirmées plusieurs fois.

1. **Donner la liste des fichiers à remplacer à chaque livraison**, plus une
   archive `.zip`. C'est une demande explicite et répétée. Le `.gitignore`
   exclut les `.zip` : une archive versionnée serait publiée dans
   `public_html` et rendrait le code source téléchargeable.
2. **Tout en français**, y compris les commentaires et les messages de
   commit. Le propriétaire n'est pas développeur : expliquer le *pourquoi*
   avant le *comment*, sans jargon.
3. **Ne jamais écrire d'identifiant de modèle d'IA** dans un commit, un
   commentaire de code, ou quoi que ce soit de versionné.
4. **Vérifier plutôt qu'affirmer.** Le proxy de l'environnement bloque
   l'accès sortant à emaee.fr : impossible de contrôler le site en ligne.
   D'où l'importance des tests et des rendus dans Chromium (voir §6).
5. **Une session ne voit pas les autres.** Chaque conversation repart de
   zéro. C'est la raison d'être de ce document : tout ce qui doit survivre
   s'écrit ici.

---

## 6. Tester

```bash
bash tests/run.sh
```

522 assertions, aucune base de données nécessaire : chaque test remplace
`db_fetch` / `db_fetch_all` / `db_execute` par une base en mémoire, puis
charge les vrais fichiers de `includes/`. Le lanceur enchaîne avec `php -l`
sur les 162 fichiers PHP.

| Fichier | Ce qu'il protège |
|---|---|
| `test_catalog.php` | le catalogue couvre bien tous les textes des pages |
| `test_equipe.php` | comptes admin, droits, journal d'activité |
| `test_inline.php` | modification directe sur la page, marqueurs invisibles |
| `test_menu.php` | menu, écrans masqués, aucun fichier d'admin oublié |
| `test_replace.php` | chercher / remplacer, et l'annulation |
| `test_routing.php` | adresses des pages et des zones |
| `test_services.php` | les 7 métiers, leurs textes, leurs adresses |
| `test_zonecontent.php` | textes pré-rédigés par zone |
| `test_zoneflow.php` | héritage, renommage, suppression d'une zone |
| `test_zones.php` | filtrage des contenus par zone |
| `test_zones_unique.php` | **la synchronisation des quatre emplacements** |

Le circuit d'intervention automatisé (§4bis) n'est pas couvert par cette
suite : la compléter de ce côté serait le premier chantier utile.

**Rendu visuel.** Chromium et Playwright sont installés
(`/opt/node22/lib/node_modules/playwright`, ne pas lancer
`playwright install`). Pour contrôler une page d'admin sans base de données :
reconstruire la page en HTML statique avec le vrai CSS, puis
`page.screenshot({fullPage:true})`. C'est ainsi qu'ont été validés le menu ☰
du site et les deux nouveaux écrans d'admin.

---

## 7. Pièges déjà rencontrés

À lire avant de déboguer quoi que ce soit de similaire.

**`backdrop-filter` casse `position: fixed`.** Un élément qui porte un
`backdrop-filter` devient le bloc conteneur de ses descendants en
`position: fixed`. Le menu ☰ est un enfant de `.site-header`, qui est flouté :
son `top:70px; bottom:68px` se calculait par rapport à l'en-tête, pas à
l'écran, et le panneau s'ouvrait en bandeau de 36 px. Corrigé en l'ancrant
sous l'en-tête (`position:absolute; top:100%`).

**Priorité des opérateurs en PHP.** `'+'.count($x)-4 .' autres'` est une
erreur fatale d'analyse. Mettre les calculs entre parenthèses dans une
concaténation.

**Ne pas minifier `style.css`.** Une minification a déjà détruit les sources
(3 lignes, dont une de 51 289 caractères) pour un gain mesuré de 2 856 octets
une fois gzippé, soit 22 %. Le serveur gzippe déjà. Garder les sources
lisibles.

**Les tests aussi ont des bugs.** Un test écrit avec des numéros de ligne en
dur a longtemps masqué trois champs non modifiables. Faire dépendre un test
de repères stables, pas de positions.

**Renommer une zone déplace ses textes.** `update_zone()` appelle
`zone_move_settings()`. Une écriture directe en base sur `zones.slug`
orpheline toutes les clés `z:ancien-slug:*`.

**« Dupliquer depuis Global » casse l'héritage.** Le bouton copie chaque
valeur dans la zone, qui n'hérite alors plus de rien. 164 textes avaient été
copiés ainsi. Le bouton « 🧹 Nettoyer » de `admin/zones_diag.php` supprime
les valeurs identiques au global et rétablit l'héritage.

**`setting()` pose des marqueurs invisibles.** Pour comparer ou traiter une
valeur, utiliser `setting_plain()` ou `raw_setting()`, jamais `setting()`.

---

## 8. Ce qui reste à faire

### Côté propriétaire du site

- [ ] **Changer le mot de passe MySQL.** Il a été exposé dans l'historique
      Git. À faire dans cPanel, puis reporter dans `config/config.local.php`
      sur le serveur. **Point de sécurité le plus urgent.**
- [ ] Ouvrir `admin/zones.php` après la mise en ligne et faire le ménage :
      masquer les doublons `77` et `Paris / Île-de-France`, renommer `Idf`
      en « Île-de-France ». Ce qui s'affiche là est exactement ce que le site
      affichera.
- [ ] Passer « 🧹 Nettoyer » sur la zone Idf (`admin/zones_diag.php`).
- [ ] Ajouter les cartes d'accueil des trois nouveaux métiers (VMC,
      portail/visiophone, automatismes) et relire leurs textes.
- [ ] Vérifier les villes reprises automatiquement sur la page Contact
      (4 par zone, 18 au maximum).

### Décisions en attente

- **`api/`** — 36 fichiers PHP, copie périmée de `admin/`. Rien n'y mène, aucun
  risque de sécurité identifié, mais c'est du poids mort qui trompe les
  recherches dans le code. En attente d'un feu vert pour suppression.
- **`tech/index.php`** — copie de la page publique faite sans corriger les
  chemins : elle charge `tech/includes/bootstrap.php`, dossier qui n'existe
  pas, donc elle plante à chaque appel. Plus référencée nulle part depuis que
  les redirections d'erreur de `intervention.php` et `pdf.php` pointent vers
  `dashboard.php`. En attente d'un feu vert pour suppression.

### Pistes proposées, jamais commencées

- Un éditeur visuel pour `pages.content_html` (aujourd'hui du HTML brut).
- Migrer les dernières listes répétables vers le type de champ `list` du
  catalogue : les 8 arguments « Pourquoi nous choisir », les cartes de
  services, les régions de la page Zones.

---

## 9. Journal des changements récents

Du plus récent au plus ancien.

| Commit | Objet |
|---|---|
| `4fc450a` | Circuit automatisé, phases 0 à 9 — autre session, voir §4bis |
| `74d4f80` | Espace technicien : 4 redirections d'erreur menaient à une page qui plante |
| `9595701` | Zones : une seule liste pour tout le site ; menu d'admin de 38 à 20 liens |
| `8695ba3` | Ne plus versionner les archives de livraison (`*.zip`) |
| `a818aea` | Menu ☰ du site : le panneau s'ouvrait en bandeau de 36 px |
| `8bbdb3f` | Carte d'avis : balise de fermeture manquante qui avalait la page |

Avant cela, dans la même série de travaux : suppression complète de la
géolocalisation (fausse à cause des VPN), comptes multi-admin avec droits par
écran et journal d'activité, tableau de bord d'accueil du back-office,
recherche limitée à la zone en cours, variables de lieu, trois nouveaux
métiers, textes des pages métier rendus modifiables un par un.
