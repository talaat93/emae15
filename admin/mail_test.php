<?php
$adminSection = 'mail_test';
require __DIR__ . '/partials/header.php';

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $to      = trim((string)($_POST['test_to'] ?? ''));
    $subject = '=?UTF-8?B?'.base64_encode('✅ Test email — '.company_name()).'?=';

    $fromName  = '=?UTF-8?B?'.base64_encode(company_name().' — Test').'?=';
    $fromEmail = company_email();

    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial;padding:32px;">';
    $html .= '<h2 style="color:#061029;">✅ Test email fonctionnel !</h2>';
    $html .= '<p>Cet email a été envoyé depuis votre site <strong>'.htmlspecialchars(company_name(),ENT_QUOTES).'</strong>.</p>';
    $html .= '<table style="margin:16px 0;border-collapse:collapse;">';
    $html .= '<tr><td style="padding:6px 12px;background:#f0f4ff;font-weight:700;">From :</td><td style="padding:6px 12px;">'.htmlspecialchars($fromEmail,ENT_QUOTES).'</td></tr>';
    $html .= '<tr><td style="padding:6px 12px;background:#f0f4ff;font-weight:700;">To :</td><td style="padding:6px 12px;">'.htmlspecialchars($to,ENT_QUOTES).'</td></tr>';
    $html .= '<tr><td style="padding:6px 12px;background:#f0f4ff;font-weight:700;">Date :</td><td style="padding:6px 12px;">'.date('d/m/Y H:i:s').'</td></tr>';
    $html .= '</table>';
    $html .= '<p style="color:#888;font-size:12px;">PHP '.PHP_VERSION.' — serveur '.gethostname().'</p>';
    $html .= '</body></html>';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/".PHP_VERSION."\r\n";

    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $ok = mail($to, $subject, $html, $headers);
        $result = $ok
            ? ['ok' => true,  'msg' => "✅ mail() a retourné TRUE — envoi vers <strong>".htmlspecialchars($to,ENT_QUOTES)."</strong>.<br>Vérifiez votre boîte de réception <strong>et vos spams</strong>."]
            : ['ok' => false, 'msg' => "❌ mail() a retourné FALSE — le serveur a refusé l'envoi.<br>Vérifiez que <strong>".htmlspecialchars($fromEmail,ENT_QUOTES)."</strong> est un compte email valide sur ce serveur cPanel."];
    } else {
        $result = ['ok' => false, 'msg' => "❌ Adresse email invalide."];
    }
}
?>
<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Outils</div>
    <h1 class="admin-page-title">Test d'envoi d'email</h1>
    <p class="admin-page-subtitle">Vérifier que PHP mail() fonctionne sur ce serveur.</p>
  </div>
</div>

<?php if ($result !== null): ?>
<div style="margin-bottom:1.5rem;padding:1rem 1.25rem;border-radius:8px;border:1px solid <?= $result['ok'] ? '#34c759' : '#ff3b30' ?>;background:<?= $result['ok'] ? '#f0fff4' : '#fff5f5' ?>;color:<?= $result['ok'] ? '#1a7a3a' : '#c0392b' ?>;">
  <?= $result['msg'] ?>
</div>
<?php endif; ?>

<div style="background:#fff8ec;border:1px solid #f0c060;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.85rem;color:#7a5200;line-height:1.7;">
  <strong>Configuration actuelle :</strong><br>
  From (expéditeur) : <code><?= e(company_email()) ?></code><br>
  Destinataire devis : <code><?= e(setting('form_email_to', company_email())) ?></code><br>
  PHP mail() disponible : <code><?= function_exists('mail') ? '✅ oui' : '❌ non (désactivé par l\'hébergeur)' ?></code>
</div>

<form method="post" class="admin-stack">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <section class="admin-panel">
    <div class="admin-panel__head"><h2>Envoyer un email de test</h2></div>
    <div class="admin-panel__body">
      <label class="admin-field">
        <span>Adresse email de test (destinataire)</span>
        <input type="email" name="test_to" value="<?= e(setting('form_email_to', company_email())) ?>" required placeholder="votre@email.fr">
      </label>
    </div>
  </section>
  <div class="admin-savebar"><button class="admin-btn admin-btn--primary" type="submit">Envoyer le test</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>