<?php
$adminSection = 'chatbot';
require __DIR__ . '/partials/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    set_setting('chatbot_enabled',        isset($_POST['chatbot_enabled']) ? '1' : '0');
    set_setting('chatbot_welcome',        trim((string)($_POST['chatbot_welcome']        ?? '')));
    set_setting('chatbot_system_prompt',  trim((string)($_POST['chatbot_system_prompt']  ?? '')));
    set_setting('chatbot_btn_label',      trim((string)($_POST['chatbot_btn_label']       ?? '')));
    set_setting('geo_default_region',     trim((string)($_POST['geo_default_region']      ?? '')));
    set_setting('geo_default_ville',      trim((string)($_POST['geo_default_ville']       ?? '')));
    set_setting('geo_default_dept',       trim((string)($_POST['geo_default_dept']        ?? '')));

    // Popup urgence
    set_setting('popup_enabled', isset($_POST['popup_enabled']) ? '1' : '0');
    set_setting('popup_delay',   trim((string)($_POST['popup_delay']   ?? '15')));
    set_setting('popup_title',   trim((string)($_POST['popup_title']   ?? '')));
    set_setting('popup_text',    trim((string)($_POST['popup_text']    ?? '')));

    // API key : enregistrer seulement si non vide (ne pas écraser par une chaîne vide)
    $newKey = trim((string)($_POST['claude_api_key'] ?? ''));
    if ($newKey !== '') set_setting('claude_api_key', $newKey);

    flash('success', 'Paramètres enregistrés.');
    redirect_to('admin/chatbot.php');
}

$enabled       = setting_bool('chatbot_enabled', false);
$welcome       = setting('chatbot_welcome',  'Bonjour ! Je suis l\'assistant EMAE. Comment puis-je vous aider ?');
$prompt        = setting('chatbot_system_prompt', '');
$btnLabel      = setting('chatbot_btn_label', '💬 Assistant');
$hasKey        = setting('claude_api_key', '') !== '';
$geoRegion     = setting('geo_default_region', 'Bourgogne-Franche-Comté et Auvergne-Rhône-Alpes');
$geoVille      = setting('geo_default_ville',  'votre région');
$geoDept       = setting('geo_default_dept',   '');
$popupEnabled  = setting_bool('popup_enabled', false);
$popupDelay    = setting('popup_delay',  '15');
$popupTitle    = setting('popup_title',  'Urgence ? On intervient dans l\'heure !');
$popupText     = setting('popup_text',   'Nos techniciens sont disponibles maintenant pour votre dépannage urgence.');
?>

<div class="admin-page-toolbar">
  <div>
    <div class="admin-breadcrumb">Marketing</div>
    <h1 class="admin-page-title">Chatbot IA</h1>
    <p class="admin-page-subtitle">Assistant virtuel Claude (Haiku) intégré sur toutes les pages du site.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a class="admin-btn admin-btn--secondary" href="<?= e(route_url('')) ?>" target="_blank">Voir le site</a>
  </div>
</div>

<form method="post" class="admin-stack">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

<!-- ACTIVATION -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Activation</h2>
    <p>Le widget apparaît en bas à droite sur toutes les pages publiques.</p>
  </div>
  <div class="admin-panel__body">
    <label class="admin-field" style="flex-direction:row;align-items:center;gap:1rem;cursor:pointer;">
      <input type="checkbox" name="chatbot_enabled" value="1" <?= $enabled ? 'checked' : '' ?> style="width:1.1rem;height:1.1rem;accent-color:var(--primary,#ee7d1a);">
      <span>Activer le chatbot sur le site</span>
    </label>
    <?php if (!$hasKey): ?>
    <div class="flash flash--error" style="margin-top:.75rem;">⚠️ Clé API Claude non configurée — le chatbot ne fonctionnera pas.</div>
    <?php else: ?>
    <div class="flash flash--success" style="margin-top:.75rem;">✓ Clé API Claude configurée.</div>
    <?php endif; ?>
  </div>
</section>

<!-- CLÉ API -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Clé API Anthropic</h2>
    <p>Votre clé API Claude (commence par <code>sk-ant-</code>). Récupérez-la sur <strong>console.anthropic.com</strong>.</p>
  </div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <span>Clé API <?= $hasKey ? '(déjà enregistrée — laisser vide pour conserver)' : '(obligatoire)' ?></span>
      <input type="password" name="claude_api_key" placeholder="sk-ant-api03-..." autocomplete="off">
    </label>
    <p class="admin-panel__helper" style="font-size:.78rem;color:var(--t2,#7B92CC);">
      Le modèle utilisé est <strong>claude-haiku-4-5</strong> (le plus rapide et économique). Coût : ~$0.001 par message.
    </p>
  </div>
</section>

<!-- APPARENCE -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Apparence du widget</h2>
  </div>
  <div class="admin-panel__body admin-form-grid admin-form-grid--2">
    <label class="admin-field">
      <span>Label du bouton</span>
      <input type="text" name="chatbot_btn_label" value="<?= e($btnLabel) ?>" placeholder="💬 Assistant">
    </label>
    <label class="admin-field">
      <span>Message de bienvenue</span>
      <input type="text" name="chatbot_welcome" value="<?= e($welcome) ?>">
    </label>
  </div>
</section>

<!-- PROMPT SYSTÈME -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>Prompt système (optionnel)</h2>
    <p>Laissez vide pour utiliser le prompt par défaut (recommandé). Personnalisez uniquement si vous voulez adapter le comportement.</p>
  </div>
  <div class="admin-panel__body">
    <label class="admin-field">
      <span>Prompt personnalisé</span>
      <textarea name="chatbot_system_prompt" rows="8" placeholder="Laisser vide = prompt EMAE par défaut"><?= e($prompt) ?></textarea>
    </label>
    <details style="margin-top:.75rem;">
      <summary style="cursor:pointer;font-size:.82rem;color:var(--t2,#7B92CC);">Voir le prompt par défaut</summary>
      <pre style="font-size:.75rem;color:var(--t2,#7B92CC);white-space:pre-wrap;margin-top:.5rem;background:rgba(255,255,255,.03);padding:.75rem;border-radius:8px;">Tu es l'assistant virtuel de <?= e(company_name()) ?>, disponible <?= e(company_hours()) ?>.
Services : électricité, plomberie, chauffage & PAC, climatisation & CVC.
Zones : <?= e(company_regions()) ?>.
Téléphone : <?= e(company_phone()) ?>.

- Réponds en français, 2-3 phrases max.
- Ne donne jamais de tarif précis.
- Pour toute urgence : oriente vers le <?= e(company_phone()) ?>.
- Questions hors sujet : redirige vers notre contact.</pre>
    </details>
  </div>
</section>

<!-- GÉOLOCALISATION PAR DÉFAUT -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>📍 Textes par défaut (IP non reconnue)</h2>
    <p>Affiché quand la géolocalisation échoue (IP mobile, VPN, hors zone). Utilisez ces valeurs dans vos textes admin avec <code>{region}</code>, <code>{ville}</code>, <code>{dept}</code>.</p>
  </div>
  <div class="admin-panel__body">
    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-field">
        <span>{region} par défaut</span>
        <input type="text" name="geo_default_region" value="<?= e($geoRegion) ?>" placeholder="Bourgogne-Franche-Comté et Auvergne-Rhône-Alpes">
      </label>
      <label class="admin-field">
        <span>{ville} par défaut</span>
        <input type="text" name="geo_default_ville" value="<?= e($geoVille) ?>" placeholder="votre région">
      </label>
      <label class="admin-field">
        <span>{dept} par défaut (optionnel)</span>
        <input type="text" name="geo_default_dept" value="<?= e($geoDept) ?>" placeholder="ex : Doubs">
      </label>
    </div>
    <div class="admin-panel__helper" style="margin-top:.75rem;font-size:.8rem;color:var(--t2,#7B92CC);">
      <strong>Comment utiliser :</strong> dans n'importe quel texte admin, tapez <code>{region}</code> pour afficher la région détectée (ou la valeur par défaut ci-dessus). Exemples : "Nous intervenons en <code>{region}</code>" — "Urgence à <code>{ville}</code>".
    </div>
  </div>
</section>

<!-- POPUP URGENCE -->
<section class="admin-panel">
  <div class="admin-panel__head">
    <h2>🚨 Popup urgence</h2>
    <p>Fenêtre qui apparaît automatiquement après quelques secondes sur toutes les pages publiques.</p>
  </div>
  <div class="admin-panel__body">
    <label class="admin-field" style="flex-direction:row;align-items:center;gap:1rem;cursor:pointer;">
      <input type="checkbox" name="popup_enabled" value="1" <?= $popupEnabled ? 'checked' : '' ?> style="width:1.1rem;height:1.1rem;accent-color:var(--primary,#ee7d1a);">
      <span>Activer le popup urgence</span>
    </label>
    <div class="admin-form-grid admin-form-grid--2" style="margin-top:1rem;">
      <label class="admin-field">
        <span>Délai avant apparition (secondes)</span>
        <input type="number" name="popup_delay" value="<?= e($popupDelay) ?>" min="5" max="120" placeholder="15">
      </label>
      <label class="admin-field">
        <span>Titre du popup</span>
        <input type="text" name="popup_title" value="<?= e($popupTitle) ?>" placeholder="Urgence ? On intervient dans l'heure !">
      </label>
    </div>
    <label class="admin-field" style="margin-top:.75rem;">
      <span>Texte du popup</span>
      <textarea name="popup_text" rows="2"><?= e($popupText) ?></textarea>
    </label>
    <p class="admin-panel__helper" style="font-size:.78rem;color:var(--t2,#7B92CC);margin-top:.5rem;">
      Le popup s'affiche une seule fois par session. Le numéro de téléphone et le bouton "Devis gratuit" sont ajoutés automatiquement.
    </p>
  </div>
</section>

<div class="admin-savebar">
  <button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button>
</div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>