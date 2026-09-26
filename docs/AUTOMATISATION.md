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
