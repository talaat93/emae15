# Circuit automatisé EMAE — mise en service

Le circuit va de l'appel du client jusqu'au paiement de la facture :

1. appel du client ;
2. qualification ;
3. fiche « À assigner » ;
4. technicien suggéré ;
5. acceptation par le technicien ;
6. rapport ;
7. relecture ;
8. brouillon de facture ;
9. validation par le dispatcher ;
10. envoi par Pennylane ;
11. paiement ;
12. clôture.

Une règle ne change jamais : **Claude assiste, il ne décide rien seul.** Aucune facture ne part sans validation d'un dispatcher, et tous les montants sont recalculés à partir de la grille tarifaire.

Sans clé API, chaque service fonctionne en **mode SIMULATION** : Claude est remplacé par un questionnaire et des contrôles standard, Pennylane et Yousign n'envoient rien de réel. Le circuit se teste donc de bout en bout sans risque.

## 1. Clés API (Dispatcher → Réglages)

Les clés sont enregistrées en base, ou dans `config/config.local.php` (clé `'secrets'`), qui n'est pas versionné. Elles ne sont jamais envoyées au navigateur.

| Service | Où créer la clé | Réglage |
|---|---|---|
| Claude | console.anthropic.com → API Keys | Réglages → Claude (modèle par défaut : `claude-opus-5`) |
| Pennylane | Pennylane → Paramètres → Connectivité → Développeurs → Générer un jeton (droits : clients, produits, factures clients en lecture et écriture) | Réglages → Pennylane ; décocher « Mode simulation » |
| Yousign | Yousign → Paramètres → Développeurs → Clés API | Réglages → Yousign ; commencer en bac à sable |

Dans chaque onglet, le bouton **« Tester la connexion »** vérifie la clé.

## 2. Tâche cron o2switch (toutes les 15 minutes)

Dans cPanel, ouvrez « Tâches cron » et ajoutez, en remplaçant `UTILISATEUR` :

```
*/15 * * * * php /home/UTILISATEUR/public_html/cron/pennylane_sync.php >> /home/UTILISATEUR/pennylane_sync.log 2>&1
```

La tâche fait trois choses :

- elle importe les clients et factures Pennylane (import complet la première fois, puis uniquement les changements) ;
- elle détecte les paiements : la fiche passe alors « Payée » puis « Clôturée » ;
- elle relit les devis Yousign en attente de signature.

Le dernier résultat s'affiche dans Réglages → Pennylane. La première synchronisation peut aussi se lancer avec le bouton « Synchroniser maintenant ».

## 3. Webhook Yousign

Dans Yousign → Paramètres → Webhooks, créez un webhook :

- URL : `https://emaee.fr/api/yousign_webhook.php` ;
- événements : `signature_request.*` (au minimum `done`, `declined`, `expired`, `canceled`) ;
- copiez le secret affiché par Yousign dans Réglages → Yousign → « Secret du webhook ».

Toute notification sans signature HMAC valide est refusée (401). L'état est toujours relu auprès de Yousign avant la moindre mise à jour.

## 4. Vérifications avant la production

- **Contrôle Pennylane en lecture seule**, sans aucune écriture dans la comptabilité :
  `PENNYLANE_TOKEN=... php cron/pennylane_check.php`
- **Première synchronisation.** Lancez-la, puis traitez dans Réglages → Pennylane les « doublons possibles » (clients de même nom et même code postal).
- **Test Yousign en bac à sable** : envoyez un devis de test à votre propre adresse, signez-le, puis vérifiez que la fiche indique « Signé », que le PDF signé est disponible et que la signature tombe bien dans le cadre « Bon pour accord ».
- **Première facture réelle** : validez-la sur un vrai dossier et contrôlez-la dans Pennylane (numéro, TVA, lignes, total).
- **Grille tarifaire** : vérifiez les prix dans Dispatcher → Tarifs.
- **Techniciens** : renseignez métiers, point de départ et horaires dans Dispatcher → Techniciens.
- **E-mails d'o2switch** : vérifiez la délivrabilité (SPF et DKIM) pour les notifications et les relances.

## 5. Journaux

Les journaux se trouvent dans `storage/logs/` (accès web refusé, jamais déployé) :

- `claude-AAAA-MM.log`
- `pennylane-AAAA-MM.log`
- `yousign-AAAA-MM.log`
- `audit-AAAA-MM.log` : validations, envois, suppressions, relances.

Ils ne contiennent aucune donnée client.

## 6. Liste des automatisations

Tout ce qui se déclenche sans clic explicite d'un dispatcher, ou qui envoie
quelque chose hors du site.

### E-mails, SMS et notifications

Les e-mails partent par la fonction `mail()` du serveur o2switch (pas de
SMTP ni de bibliothèque), via `send_quote_notification()` ou `notif_mail()`.

| Déclencheur | Destinataire | Fonction |
|---|---|---|
| Demande de devis sur le site | entreprise (+ accusé au client) | `send_quote_notification()`, `send_quote_confirmation_to_client()` |
| Intervention attribuée | technicien : notification, e-mail, SMS selon les réglages | `notify_intervention_assigned()` |
| Date et technicien fixés | client : e-mail ou SMS | `notify_client_scheduled()` |
| Technicien « Je pars » | client | `notify_client_en_route()` |
| Refus d'une intervention par le technicien | dispatcher | `notify_dispatcher_refusal()` |
| Rapport rendu | dispatcher | `notify_report_submitted()` |
| Rapport incomplet | technicien | `notify_tech_report_incomplete()` |
| Facture envoyée | copie au technicien | `invoice_copy_to_tech()` |
| Relance de facture (déclenchée par le dispatcher) | client — ou l'adresse de test en simulation | `reminder_send()`, `reminder_send_texte()` |
| Devis signé, refusé ou expiré | dispatcher | `devis_notify_dispatcher()` |
| Nouvelle tâche | dispatcher | `notify_task_created()` |

En **mode simulation**, les relances ne partent jamais chez le client :
elles vont à l'« adresse de test » des réglages, ou nulle part.

### Tâches planifiées et webhooks

| Quoi | Quand | Fichier |
|---|---|---|
| Synchronisation Pennylane (clients, factures, paiements, produits) et relecture des devis en attente | toutes les 15 min (cron o2switch) | `cron/pennylane_sync.php` |
| Paiement constaté dans Pennylane → intervention « Payée » puis « Clôturée » | à chaque synchronisation | `pennylane_sync_intervention_status()` |
| Brouillons de facture jamais arrivés dans Pennylane → nouvel essai | à chaque synchronisation | `pennylane_sync_run()` |
| Devis signé, refusé ou expiré | à la réception du webhook Yousign | `api/yousign_webhook.php` |
| Relecture du rapport (Claude ou contrôles standard) → brouillon de facture | juste après la remise du rapport, en arrière-plan | `review_run_after_response()` |

### Migrations de la base

Au premier chargement d'une page après une mise en ligne,
`includes/bootstrap.php` crée les tables et colonnes manquantes. Chaque
migration ne s'exécute qu'une fois, grâce à un fichier témoin
`storage/.mig_v15_xxx`, jamais déployé. Elles n'effacent jamais de données ;
les rejouer est sans effet. Les plus récentes : v15.21 (grille tarifaire) à
v15.30 (tables `integration_settings` et `integration_log`).

### Vérifications manuelles disponibles

| Commande ou écran | Effet |
|---|---|
| Réglages → Pennylane → « Tester la connexion » | teste 6 routes de lecture, route par route |
| `PENNYLANE_TOKEN=… php cron/pennylane_check.php` | contrôle complet en lecture seule, sur le serveur ou en local |
| Factures → « Pennylane en direct » | liste des factures lue directement dans Pennylane, sans rien modifier |
| `bash tests/run.sh` | suite de tests, dont la simulation Pennylane et les relances |
