<?php
declare(strict_types=1);
$pageTitle = 'Clients';
$dispSection = 'clients';
require __DIR__.'/partials/header.php';

// Handle POST: create client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_client') {
    verify_csrf();
    $data = [
        'lastname'    => trim((string)($_POST['lastname']    ?? '')),
        'firstname'   => trim((string)($_POST['firstname']   ?? '')),
        'phone'       => trim((string)($_POST['phone']       ?? '')),
        'email'       => trim((string)($_POST['email']       ?? '')),
        'address'     => trim((string)($_POST['address']     ?? '')),
        'postal_code' => trim((string)($_POST['postal_code'] ?? '')),
        'city'        => trim((string)($_POST['city']        ?? '')),
        'floor'       => trim((string)($_POST['floor']       ?? '')),
        'digicode'    => trim((string)($_POST['digicode']    ?? '')),
        'access_info' => trim((string)($_POST['access_info'] ?? '')),
        'notes'       => trim((string)($_POST['notes']       ?? '')),
    ];
    if ($data['lastname'] === '' || $data['phone'] === '') {
        flash('error', 'Le nom et le téléphone sont obligatoires.');
    } else {
        $newId = create_client($data);
        flash('success', 'Client créé avec succès.');
        redirect_to('dispatcher/client_view.php?id=' . $newId);
    }
}

// Search / list
$q = trim((string)($_GET['q'] ?? ''));
$clients = $q !== '' ? search_clients($q) : all_clients_list(300);
$showForm = !empty($_GET['new']) || !empty($_POST['show_form']);
?>

<div class="d-topbar">
  <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="d-topbar-title">
    <span class="d-topbar-ico">👥</span> Clients
  </div>
  <div class="d-topbar-actions">
    <button onclick="toggleForm()" class="d-btn d-btn-primary d-btn-sm" id="btn-new">
      + Nouveau client
    </button>
  </div>
</div>

<div class="d-content" style="padding:1.5rem 1.75rem;">

  <!-- Formulaire nouveau client (masqué par défaut) -->
  <div id="form-nouveau-client" style="display:<?= $showForm ? 'block' : 'none' ?>;margin-bottom:1.5rem;">
    <div class="d-card">
      <div class="d-card-header">
        <span class="d-card-title">Nouveau client</span>
        <button onclick="toggleForm()" style="background:none;border:none;color:#8fa0c4;cursor:pointer;font-size:1.2rem;">✕</button>
      </div>
      <div class="d-card-body">
        <form method="post" id="form-client">
          <input type="hidden" name="action" value="create_client">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
            <div class="d-field">
              <label class="d-label">Nom *</label>
              <input type="text" name="lastname" class="d-input" required placeholder="Dupont" value="<?= e($_POST['lastname'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Prénom</label>
              <input type="text" name="firstname" class="d-input" placeholder="Jean" value="<?= e($_POST['firstname'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Téléphone *</label>
              <input type="tel" name="phone" class="d-input" required placeholder="06 00 00 00 00" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Email</label>
              <input type="email" name="email" class="d-input" placeholder="jean@example.fr" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Adresse</label>
              <input type="text" name="address" class="d-input" placeholder="12 rue de la Paix" value="<?= e($_POST['address'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Code postal</label>
              <input type="text" name="postal_code" class="d-input" placeholder="75001" value="<?= e($_POST['postal_code'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Ville</label>
              <input type="text" name="city" class="d-input" placeholder="Paris" value="<?= e($_POST['city'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Étage / Bât.</label>
              <input type="text" name="floor" class="d-input" placeholder="3ème gauche" value="<?= e($_POST['floor'] ?? '') ?>">
            </div>
            <div class="d-field">
              <label class="d-label">Digicode</label>
              <input type="text" name="digicode" class="d-input" placeholder="A1234" value="<?= e($_POST['digicode'] ?? '') ?>">
            </div>
          </div>
          <div class="d-field" style="margin-top:1rem;">
            <label class="d-label">Infos d'accès</label>
            <input type="text" name="access_info" class="d-input" placeholder="Sonner à la conciergerie…" value="<?= e($_POST['access_info'] ?? '') ?>">
          </div>
          <div class="d-field" style="margin-top:.75rem;">
            <label class="d-label">Notes internes</label>
            <textarea name="notes" class="d-input d-textarea" rows="2" placeholder="Informations supplémentaires…"><?= e($_POST['notes'] ?? '') ?></textarea>
          </div>

          <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
            <button type="submit" class="d-btn d-btn-primary">✓ Créer le client</button>
            <button type="button" onclick="toggleForm()" class="d-btn d-btn-outline">Annuler</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Recherche -->
  <div class="d-card" style="margin-bottom:1.25rem;">
    <div class="d-card-body" style="padding:.85rem 1.25rem;">
      <form method="get" style="display:flex;gap:.75rem;align-items:center;">
        <input type="text" name="q" value="<?= e($q) ?>" class="d-input" style="flex:1;max-width:420px;"
               placeholder="Rechercher par nom, prénom, téléphone, ville…">
        <button type="submit" class="d-btn d-btn-primary d-btn-sm">🔍 Rechercher</button>
        <?php if ($q !== ''): ?>
        <a href="<?= e(url_for('dispatcher/clients.php')) ?>" class="d-btn d-btn-outline d-btn-sm">✕ Effacer</a>
        <?php endif; ?>
      </form>
      <?php if ($q !== ''): ?>
      <div style="margin-top:.5rem;font-size:.8rem;color:#8fa0c4;"><?= count($clients) ?> résultat<?= count($clients) > 1 ? 's' : '' ?> pour « <?= e($q) ?> »</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tableau clients -->
  <div class="d-card">
    <div class="d-card-header">
      <span class="d-card-title">Clients (<?= count($clients) ?>)</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="d-table">
        <thead>
          <tr>
            <th>Nom / Prénom</th>
            <th>Téléphone</th>
            <th>Ville</th>
            <th style="text-align:center;">Interventions</th>
            <th>Créé le</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($clients)): ?>
          <tr>
            <td colspan="6" style="text-align:center;padding:3rem 0;color:#8fa0c4;">
              <div style="font-size:2rem;margin-bottom:.5rem;">👥</div>
              <?= $q !== '' ? 'Aucun client trouvé pour cette recherche.' : 'Aucun client enregistré.' ?>
              <?php if ($q === ''): ?><br><button onclick="toggleForm()" class="d-btn d-btn-primary d-btn-sm" style="margin-top:.75rem;">+ Créer le premier client</button><?php endif; ?>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($clients as $client): ?>
          <?php
            $fullName = trim($client['lastname'].' '.($client['firstname'] ?? ''));
            $city = trim(($client['postal_code'] ?? '').' '.($client['city'] ?? ''));
            $intCount = client_intervention_count((int)$client['id']);
            $initials = mb_strtoupper(mb_substr($client['lastname'], 0, 1, 'UTF-8'), 'UTF-8');
            if (!empty($client['firstname'])) $initials .= mb_strtoupper(mb_substr($client['firstname'], 0, 1, 'UTF-8'), 'UTF-8');
            $createdAt = !empty($client['created_at']) ? date('d/m/Y', strtotime($client['created_at'])) : '—';
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.65rem;">
                <div style="width:34px;height:34px;border-radius:50%;background:rgba(238,125,26,.2);color:#ee7d1a;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;flex-shrink:0;"><?= e($initials) ?></div>
                <div>
                  <div style="font-weight:700;color:#e8ecf5;font-size:.88rem;"><?= e($fullName) ?></div>
                  <?php if (!empty($client['email'])): ?>
                  <div style="font-size:.72rem;color:#8fa0c4;"><?= e($client['email']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <a href="tel:<?= e(preg_replace('/\s+/', '', $client['phone'])) ?>" style="color:#ee7d1a;text-decoration:none;font-weight:600;font-size:.85rem;">
                <?= e($client['phone']) ?>
              </a>
            </td>
            <td style="color:#8fa0c4;font-size:.85rem;"><?= $city !== '' ? e($city) : '—' ?></td>
            <td style="text-align:center;">
              <?php if ($intCount > 0): ?>
                <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:rgba(59,130,246,.15);color:#60a5fa;font-size:.78rem;font-weight:700;"><?= $intCount ?></span>
              <?php else: ?>
                <span style="color:#8fa0c4;font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>
            <td style="color:#8fa0c4;font-size:.82rem;"><?= e($createdAt) ?></td>
            <td style="text-align:right;">
              <div style="display:flex;gap:.4rem;justify-content:flex-end;">
                <a href="<?= e(url_for('dispatcher/client_view.php?id='.(int)$client['id'])) ?>" class="d-btn d-btn-outline d-btn-xs">
                  Voir
                </a>
                <a href="<?= e(url_for('dispatcher/intervention_new.php?client_id='.(int)$client['id'])) ?>" class="d-btn d-btn-primary d-btn-xs">
                  + Intervention
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function toggleForm() {
  var f = document.getElementById('form-nouveau-client');
  var isVisible = f.style.display !== 'none';
  f.style.display = isVisible ? 'none' : 'block';
  if (!isVisible) f.querySelector('input[name="lastname"]').focus();
}
<?php if ($showForm): ?>
document.getElementById('form-nouveau-client').style.display = 'block';
<?php endif; ?>
</script>

<?php require __DIR__.'/partials/footer.php'; ?>
