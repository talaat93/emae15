<?php
/**
 * Paiements en ligne (Stripe) : réglages, création des liens de paiement, suivi.
 * Voir includes/payments.php.
 */
$adminSection = 'payments';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'settings') {
        growth_save('payments_enabled', !empty($_POST['payments_enabled']) ? '1' : '0');
        foreach (['stripe_secret_key', 'stripe_webhook_secret'] as $k) {
            $val = trim((string)($_POST[$k] ?? ''));
            if ($val !== '') set_integration_secret($k, $val);
            if (!empty($_POST[$k.'_clear'])) set_integration_secret($k, '');
        }
        if (trim((string)($_POST['stripe_secret_key'] ?? '')) !== '' && !str_starts_with(trim((string)$_POST['stripe_secret_key']), 'sk_')) {
            flash('error', 'La clé secrète Stripe commence par « sk_live_ » (ou « sk_test_ » pour les essais).');
        } else {
            flash('success', 'Réglages enregistrés.');
        }
        redirect_to('admin/payments.php');
    }
    if ($act === 'create') {
        $admin = (string)(current_admin()['name'] ?? current_admin()['email'] ?? '');
        $token = payment_create([
            'amount_cents' => payment_amount_cents((string)($_POST['amount'] ?? '')),
            'description'  => (string)($_POST['description'] ?? ''),
            'client_name'  => (string)($_POST['client_name'] ?? ''),
            'phone'        => (string)($_POST['phone'] ?? ''),
            'email'        => (string)($_POST['email'] ?? ''),
            'invoice_id'   => (int)($_POST['invoice_id'] ?? 0),
            'created_by'   => (string)$admin,
        ]);
        if ($token === '') { flash('error', 'Indiquez un libellé et un montant d\'au moins 1 €.'); redirect_to('admin/payments.php'); }
        $url = payment_url($token);
        $sent = [];
        if (!empty($_POST['send_sms']) && trim((string)$_POST['phone']) !== '' && sms_configured()) {
            if (notif_sms((string)$_POST['phone'], company_name().' : voici votre lien de paiement sécurisé ('.money_fr(payment_amount_cents((string)$_POST['amount']) / 100).') : '.$url)) $sent[] = 'SMS';
        }
        if (!empty($_POST['send_email']) && filter_var(trim((string)$_POST['email']), FILTER_VALIDATE_EMAIL)) {
            if (notif_mail(trim((string)$_POST['email']), 'Votre lien de paiement — '.company_name(), 'Votre lien de paiement',
                ['Bonjour,', 'Vous pouvez régler <b>'.e(money_fr(payment_amount_cents((string)$_POST['amount']) / 100)).'</b> ('.e(trim((string)$_POST['description'])).') par carte bancaire, en toute sécurité.'], $url, 'Payer en ligne')) $sent[] = 'e-mail';
        }
        flash('success', 'Lien créé'.($sent ? ' et envoyé par '.implode(' et ', $sent) : '').' : '.$url);
        redirect_to('admin/payments.php');
    }
    if ($act === 'cancel') {
        payments_table();
        db_execute("UPDATE online_payments SET status = 'annulé' WHERE id = ? AND status = 'en_attente'", [(int)($_POST['id'] ?? 0)]);
        flash('success', 'Lien annulé.');
        redirect_to('admin/payments.php');
    }
}
$configured = payments_configured();
$list = payments_list();
$hook = notif_abs_url('api/stripe_webhook.php');
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Croissance</div>
    <h1 class="admin-page-title">Paiements en ligne</h1>
    <p class="admin-page-subtitle">Envoyez un lien de paiement par SMS ou e-mail : le client règle par carte sur la page sécurisée de Stripe.</p>
  </div>
</div>

<?php if (!$configured): ?>
<div style="background:#eef6ff;border:1px solid #bcd8ee;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.9rem;color:#0f4c75;line-height:1.7;">
  <strong>Pour démarrer :</strong><br>
  1. Créez un compte sur <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">stripe.com</a> et renseignez votre entreprise et votre IBAN.<br>
  2. Dans Stripe → Développeurs → Clés API, copiez la <b>clé secrète</b> (sk_live_…) ci-dessous.<br>
  3. Dans Stripe → Développeurs → Webhooks, ajoutez l'adresse <code><?= e($hook) ?></code> avec l'événement <code>checkout.session.completed</code>, puis copiez le secret (whsec_…) ci-dessous.<br>
  4. Cochez « Activer » et enregistrez. Faites un premier essai avec une petite somme.
</div>
<?php endif; ?>

<form method="post" class="admin-panel">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="settings">
  <div class="admin-panel__head"><h2>🔑 Réglages Stripe</h2></div>
  <div class="admin-panel__body">
    <label class="admin-field admin-field--check"><div class="check-row"><input type="checkbox" name="payments_enabled" value="1" <?= global_setting('payments_enabled', '0') === '1' ? 'checked' : '' ?>> Activer le paiement en ligne</div></label>
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Clé secrète — <?= $configured ? '<b style="color:#15803d;">configurée</b>' : 'non configurée' ?></span><input type="password" name="stripe_secret_key" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer"></label>
      <label class="admin-field"><span>Secret du webhook — <?= integration_secret_configured('stripe_webhook_secret') ? '<b style="color:#15803d;">configuré</b>' : 'non configuré' ?></span><input type="password" name="stripe_webhook_secret" autocomplete="new-password" placeholder="whsec_…"></label>
    </div>
    <?php if ($configured): ?><label class="check-row"><input type="checkbox" name="stripe_secret_key_clear" value="1"> Supprimer la clé secrète</label><?php endif; ?>
  </div>
  <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">💾 Enregistrer</button></div>
</form>

<form method="post" class="admin-panel">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="create">
  <div class="admin-panel__head"><h2>➕ Nouveau lien de paiement</h2><?php if (!payments_enabled()): ?><p style="color:#b45309;">Le lien peut être créé, mais le client ne pourra payer qu'une fois Stripe configuré et activé.</p><?php endif; ?></div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field"><span>Libellé *</span><input type="text" name="description" required placeholder="Facture F-2026-0142 — dépannage chaudière"></label>
      <label class="admin-field"><span>Montant TTC (€) *</span><input type="text" name="amount" required inputmode="decimal" placeholder="185,00"></label>
      <label class="admin-field"><span>Client</span><input type="text" name="client_name"></label>
      <label class="admin-field"><span>Mobile</span><input type="tel" name="phone"></label>
      <label class="admin-field"><span>E-mail</span><input type="email" name="email"></label>
      <label class="admin-field"><span>N° de facture interne (facultatif)</span><input type="number" name="invoice_id" min="0"></label>
    </div>
    <div class="check-row" style="gap:1.2rem;flex-wrap:wrap;">
      <label><input type="checkbox" name="send_sms" value="1" <?= sms_configured() ? '' : 'disabled' ?>> Envoyer par SMS<?= sms_configured() ? '' : ' (compte SMS non configuré)' ?></label>
      <label><input type="checkbox" name="send_email" value="1"> Envoyer par e-mail</label>
    </div>
  </div>
  <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Créer le lien</button></div>
</form>

<section class="admin-panel">
  <div class="admin-panel__head"><h2>Liens de paiement</h2><p>Un paiement reçu vous est aussi signalé par e-mail. Pensez à marquer la facture payée dans Pennylane ou dans Factures.</p></div>
  <div class="admin-panel__body admin-table-wrap">
    <?php if (!$list): ?><p style="color:#888;">Aucun lien pour le moment.</p><?php else: ?>
    <table class="admin-table"><thead><tr><th>Créé le</th><th>Libellé</th><th>Client</th><th>Montant</th><th>État</th><th>Lien</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $p): ?>
      <tr><td><?= e(date('d/m/Y', strtotime((string)$p['created_at']))) ?></td><td><?= e((string)$p['description']) ?></td><td><?= e(trim((string)$p['client_name'].' '.(string)$p['phone'])) ?></td>
      <td><strong><?= e(money_fr($p['amount_cents'] / 100)) ?></strong></td>
      <td><?= $p['status'] === 'payé' ? '✅ Payé le '.e(date('d/m', strtotime((string)$p['paid_at']))) : ($p['status'] === 'annulé' ? 'Annulé' : '⏳ En attente') ?></td>
      <td><input type="text" readonly value="<?= e(payment_url((string)$p['token'])) ?>" onclick="this.select();" style="width:220px;font-size:.75rem;"></td>
      <td><?php if ($p['status'] === 'en_attente'): ?><form method="post" onsubmit="return confirm('Annuler ce lien ?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="act" value="cancel"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="admin-btn admin-btn--secondary" type="submit">Annuler</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
