<?php
declare(strict_types=1);
$pageTitle   = 'Nouvelle intervention';
$dispSection = 'intervention_new';
require __DIR__.'/partials/header.php';

/* ─────────────────────────────────────────────────────
   POST — Création
───────────────────────────────────────────────────── */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $clientMode = trim((string)($_POST['client_mode'] ?? 'existing'));

    /* ── Client ── */
    $clientId = (int)($_POST['client_id'] ?? 0);

    if ($clientMode === 'new') {
        $newLastname = trim((string)($_POST['new_lastname'] ?? ''));
        $newPhone    = trim((string)($_POST['new_phone']    ?? ''));
        if ($newLastname === '') $errors[] = 'Le nom du client est requis.';
        if ($newPhone    === '') $errors[] = 'Le téléphone du client est requis.';

        if (empty($errors)) {
            $clientId = create_client([
                'lastname'    => $newLastname,
                'firstname'   => trim((string)($_POST['new_firstname']   ?? '')),
                'phone'       => $newPhone,
                'email'       => trim((string)($_POST['new_email']       ?? '')),
                'address'     => trim((string)($_POST['new_address']     ?? '')),
                'postal_code' => trim((string)($_POST['new_postal_code'] ?? '')),
                'city'        => trim((string)($_POST['new_city']        ?? '')),
                'floor'       => trim((string)($_POST['new_floor']       ?? '')),
                'digicode'    => trim((string)($_POST['new_digicode']    ?? '')),
                'access_info' => trim((string)($_POST['new_access_info'] ?? '')),
                'notes'       => '',
            ]);
        }
    } elseif ($clientId <= 0) {
        $errors[] = 'Veuillez sélectionner ou créer un client.';
    }

    /* ── Création intervention ── */
    if (empty($errors)) {
        $urgencyVal    = !empty($_POST['urgency']) ? 1 : 0;
        $quoteAccepted = !empty($_POST['quote_accepted']) ? 1 : 0;
        $techId        = (int)($_POST['technician_id'] ?? 0);
        $amountHt      = trim((string)($_POST['amount_ht'] ?? ''));

        $data = [
            'client_id'          => $clientId,
            'dispatcher_id'      => (int)$disp['id'],
            'technician_id'      => $techId ?: null,
            'scheduled_date'     => trim((string)($_POST['scheduled_date']     ?? '')),
            'scheduled_time'     => trim((string)($_POST['scheduled_time']     ?? '')),
            'duration_estimate'  => (int)($_POST['duration_estimate']          ?? 60),
            'urgency'            => $urgencyVal,
            'priority'           => trim((string)($_POST['priority']           ?? 'normale')),
            'category'           => trim((string)($_POST['category']           ?? '')),
            'type_label'         => trim((string)($_POST['type_label_custom'] ?? $_POST['type_label'] ?? '')),
            'installation_type'  => trim((string)($_POST['installation_type']  ?? '')),
            'fault_reported'     => trim((string)($_POST['fault_reported']     ?? '')),
            'description'        => trim((string)($_POST['description']        ?? '')),
            'materials_needed'   => trim((string)($_POST['materials_json'] ?? $_POST['materials_needed'] ?? '')),
            'notes_admin'        => trim((string)($_POST['notes_admin']        ?? '')),
            'quote_accepted'     => $quoteAccepted,
            'amount_ht'          => $amountHt !== '' ? $amountHt : null,
            'payment_method'     => trim((string)($_POST['payment_method']     ?? '')),
            'status'             => 'nouveau',
            'latitude'           => trim((string)($_POST['latitude']           ?? '')),
            'longitude'          => trim((string)($_POST['longitude']          ?? '')),
        ];

        $ivId = create_intervention($data);
        log_intervention_history($ivId, null, 'nouveau', 'dispatcher', (int)$disp['id'], (string)$disp['name'], 'Création de l\'intervention');

        /* Si technicien assigné → statut assigné */
        if ($techId > 0) {
            update_intervention($ivId, ['status' => 'assigné']);
            log_intervention_history($ivId, 'nouveau', 'assigné', 'dispatcher', (int)$disp['id'], (string)$disp['name'], 'Technicien assigné à la création');

            /* SMS au technicien */
            $tech = get_tech_by_id($techId);
            if ($tech && !empty($tech['phone'])) {
                $iv = get_intervention_by_id($ivId);
                $client = get_client_by_id($clientId);
                $smsMsg = company_name().' — Nouvelle intervention '
                    . ($iv['ref'] ?? '#'.$ivId)
                    . ($urgencyVal ? ' [URGENT]' : '')
                    . '. Client: '.($client['lastname'] ?? '').' '.($client['firstname'] ?? '')
                    . '. Tél: '.($client['phone'] ?? '')
                    . '. Adresse: '.($client['address'] ?? '').' '.($client['postal_code'] ?? '').' '.($client['city'] ?? '')
                    . ($data['scheduled_date'] ? '. Date: '.date('d/m/Y', strtotime($data['scheduled_date'])) : '');
                send_sms_dispatcher((string)$tech['phone'], mb_substr($smsMsg, 0, 160));
            }
        }

        flash('success', 'Intervention créée avec succès.');
        redirect_to('dispatcher/intervention_view.php?id='.$ivId);
    }
}

$techs   = all_technicians();
$catsCfg = intervention_category_config();
$statCfg = intervention_status_config();

/* Repopulate POST values after error */
$post = $_POST;
?>

<!-- TOPBAR -->
<div class="d-topbar">
  <div style="display:flex;align-items:center;gap:.75rem;">
    <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">☰</button>
    <div class="d-topbar-title">➕ Nouvelle intervention</div>
  </div>
  <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--secondary d-btn--sm">← Retour</a>
</div>

<div class="d-content">

<?php if (!empty($errors)): ?>
  <div class="d-flash d-flash--error" style="margin-bottom:1.25rem;">
    ⚠️ <?= implode(' • ', array_map('e', $errors)) ?>
  </div>
<?php endif; ?>

<form method="post" id="form-new-interv" autocomplete="off">
  <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="client_id"   id="inp-client-id" value="<?= e($post['client_id'] ?? '') ?>">
  <input type="hidden" name="latitude"    id="inp-lat"       value="<?= e($post['latitude']  ?? '') ?>">
  <input type="hidden" name="longitude"   id="inp-lng"       value="<?= e($post['longitude'] ?? '') ?>">

  <!-- ═══════════════════════════════════════════
       SECTION 1 — CLIENT
  ══════════════════════════════════════════════ -->
  <div class="d-card d-form-section" style="margin-bottom:1.25rem;">
    <div class="d-card-head">
      <div class="d-card-title">👤 Section 1 — Client</div>
    </div>
    <div class="d-card-body">
      <div class="d-form-section-title">Mode client</div>

      <!-- Radio -->
      <div style="display:flex;gap:1.25rem;margin-bottom:1.25rem;">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.9rem;color:#e8ecf5;">
          <input type="radio" name="client_mode" value="existing" id="r-existing"
                 <?= (($post['client_mode'] ?? 'existing') === 'existing') ? 'checked' : '' ?>
                 style="width:auto;accent-color:#F07B1D;">
          Client existant
        </label>
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.9rem;color:#e8ecf5;">
          <input type="radio" name="client_mode" value="new" id="r-new"
                 <?= (($post['client_mode'] ?? '') === 'new') ? 'checked' : '' ?>
                 style="width:auto;accent-color:#F07B1D;">
          Nouveau client
        </label>
      </div>

      <!-- Client existant -->
      <div id="block-existing" style="display:<?= (($post['client_mode'] ?? 'existing') === 'existing') ? 'block' : 'none' ?>;">
        <div class="d-form-section-title">Rechercher un client</div>
        <div class="d-field" style="position:relative;">
          <label for="client-search">Nom, téléphone, ville…</label>
          <input type="text" id="client-search" placeholder="Commencez à taper…" autocomplete="off">
          <div id="client-dropdown" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:50;background:#061029;border:1px solid rgba(255,255,255,.12);border-radius:8px;max-height:220px;overflow-y:auto;margin-top:2px;"></div>
        </div>
        <div id="client-selected" style="display:none;background:rgba(240,123,29,.08);border:1px solid rgba(240,123,29,.2);border-radius:8px;padding:.85rem 1rem;font-size:.88rem;color:#e8ecf5;margin-top:.25rem;">
          <span id="client-selected-text"></span>
          <button type="button" id="client-clear" style="margin-left:1rem;background:transparent;border:none;color:#8fa0c4;cursor:pointer;font-size:.8rem;">✕ Changer</button>
        </div>
      </div>

      <!-- Nouveau client -->
      <div id="block-new" style="display:<?= (($post['client_mode'] ?? '') === 'new') ? 'block' : 'none' ?>;">
        <div class="d-form-section-title">Informations du nouveau client</div>
        <div class="d-grid-2">
          <div class="d-field">
            <label>Nom <span style="color:#ef4444">*</span></label>
            <input type="text" name="new_lastname" value="<?= e($post['new_lastname'] ?? '') ?>" placeholder="Dupont">
          </div>
          <div class="d-field">
            <label>Prénom</label>
            <input type="text" name="new_firstname" value="<?= e($post['new_firstname'] ?? '') ?>" placeholder="Jean">
          </div>
        </div>
        <div class="d-grid-2">
          <div class="d-field">
            <label>Téléphone <span style="color:#ef4444">*</span></label>
            <input type="tel" name="new_phone" value="<?= e($post['new_phone'] ?? '') ?>" placeholder="06 12 34 56 78">
          </div>
          <div class="d-field">
            <label>Email</label>
            <input type="email" name="new_email" value="<?= e($post['new_email'] ?? '') ?>" placeholder="jean.dupont@mail.com">
          </div>
        </div>
        <div class="d-field" id="field-address">
          <label>Adresse</label>
          <input type="text" name="new_address" id="new-address" value="<?= e($post['new_address'] ?? '') ?>" placeholder="12 rue de la Paix">
        </div>
        <div class="d-grid-2">
          <div class="d-field">
            <label>Code postal</label>
            <input type="text" name="new_postal_code" id="new-postal-code" value="<?= e($post['new_postal_code'] ?? '') ?>" placeholder="75001">
          </div>
          <div class="d-field">
            <label>Ville</label>
            <input type="text" name="new_city" id="new-city" value="<?= e($post['new_city'] ?? '') ?>" placeholder="Paris">
          </div>
        </div>
        <div class="d-grid-2">
          <div class="d-field">
            <label>Étage / Bâtiment</label>
            <input type="text" name="new_floor" value="<?= e($post['new_floor'] ?? '') ?>" placeholder="3ème étage, Bât. B">
          </div>
          <div class="d-field">
            <label>Digicode</label>
            <input type="text" name="new_digicode" value="<?= e($post['new_digicode'] ?? '') ?>" placeholder="A1234">
          </div>
        </div>
        <div class="d-field">
          <label>Informations d'accès</label>
          <textarea name="new_access_info" placeholder="Interphone, gardien, clé chez voisin…"><?= e($post['new_access_info'] ?? '') ?></textarea>
        </div>
        <button type="button" id="btn-geocode" class="d-btn d-btn--secondary d-btn--sm">
          📍 Géolocaliser l'adresse
        </button>
        <span id="geocode-result" style="margin-left:.75rem;font-size:.8rem;color:#8fa0c4;"></span>
      </div>

    </div>
  </div>

  <!-- ═══════════════════════════════════════════
       SECTION 2 — PLANIFICATION
  ══════════════════════════════════════════════ -->
  <div class="d-card d-form-section" style="margin-bottom:1.25rem;">
    <div class="d-card-head">
      <div class="d-card-title">📅 Section 2 — Planification</div>
    </div>
    <div class="d-card-body">
      <div class="d-grid-3">
        <div class="d-field">
          <label>Date planifiée</label>
          <input type="date" name="scheduled_date" value="<?= e($post['scheduled_date'] ?? '') ?>">
        </div>
        <div class="d-field">
          <label>Heure</label>
          <input type="time" name="scheduled_time" step="900" value="<?= e($post['scheduled_time'] ?? '') ?>">
        </div>
        <div class="d-field">
          <label>Durée estimée</label>
          <select name="duration_estimate">
            <?php $durSel = (int)($post['duration_estimate'] ?? 60);
            foreach ([
              30=>'30 min', 60=>'1 heure', 90=>'1h30', 120=>'2 heures',
              180=>'3 heures', 240=>'Demi-journée (4h)', 480=>'Journée complète (8h)',
            ] as $dv => $dl): ?>
              <option value="<?= $dv ?>" <?= $durSel === $dv ? 'selected' : '' ?>><?= e($dl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="d-grid-3">
        <div class="d-field">
          <label>Priorité</label>
          <select name="priority">
            <?php $prioSel = $post['priority'] ?? 'normale';
            foreach (['basse'=>'Basse','normale'=>'Normale','haute'=>'Haute','urgente'=>'Urgente'] as $pv => $pl): ?>
              <option value="<?= e($pv) ?>" <?= $prioSel === $pv ? 'selected' : '' ?>><?= e($pl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-field">
          <label>Technicien</label>
          <select name="technician_id">
            <option value="">Non assigné</option>
            <?php foreach ($techs as $t): ?>
              <option value="<?= (int)$t['id'] ?>"
                      <?= ((int)($post['technician_id'] ?? 0)) === (int)$t['id'] ? 'selected' : '' ?>>
                <?= e($t['name']) ?><?= $t['status'] !== 'actif' ? ' (inactif)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-field" style="display:flex;align-items:center;gap:.65rem;padding-top:1.8rem;">
          <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;color:#e8ecf5;margin-bottom:0;">
            <input type="checkbox" name="urgency" value="1"
                   <?= !empty($post['urgency']) ? 'checked' : '' ?>
                   style="width:auto;accent-color:#ef4444;">
            🚨 Urgence
          </label>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════
       SECTION 3 — TECHNIQUE
  ══════════════════════════════════════════════ -->
  <div class="d-card d-form-section" style="margin-bottom:1.25rem;">
    <div class="d-card-head">
      <div class="d-card-title">🔧 Section 3 — Technique</div>
    </div>
    <div class="d-card-body">
      <div class="d-grid-2">
        <div class="d-field">
          <label>Catégorie</label>
          <select name="category" id="category-select">
            <option value="">Choisir une catégorie</option>
            <?php $catSel = $post['category'] ?? '';
            foreach ($catsCfg as $ck => $cv): ?>
              <option value="<?= e($ck) ?>" <?= $catSel === $ck ? 'selected' : '' ?>>
                <?= e($cv['icon'].' '.$cv['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:.5rem;align-items:flex-end;">
          <div class="d-field" style="flex:1;margin-bottom:0;">
            <label>Type d'intervention</label>
            <select name="type_label" id="type_label_select" class="d-input">
              <option value="">— Choisir —</option>
              <?php if (!empty($post['type_label'])): ?>
                <option value="<?= e($post['type_label']) ?>" selected><?= e($post['type_label']) ?></option>
              <?php endif; ?>
            </select>
            <input type="text" name="type_label_custom" id="type_label_custom" class="d-input"
                   placeholder="Saisir un type personnalisé…"
                   style="display:none;margin-top:.35rem;"
                   value="">
          </div>
          <button type="button" class="d-btn d-btn--ghost" id="btn-add-type-preset"
                  style="margin-bottom:0;flex-shrink:0;padding:.55rem .9rem;" title="Ajouter ce type aux presets">＋</button>
        </div>
      </div>
      <div class="d-field">
        <label>Type d'installation</label>
        <input type="text" name="installation_type" value="<?= e($post['installation_type'] ?? '') ?>"
               placeholder="Ex: Tableau électrique Legrand 18 modules">
      </div>
      <div class="d-field">
        <label>Panne signalée</label>
        <textarea name="fault_reported" placeholder="Description de la panne signalée par le client…"><?= e($post['fault_reported'] ?? '') ?></textarea>
      </div>
      <div class="d-field">
        <label>Description détaillée</label>
        <textarea name="description" placeholder="Informations complémentaires, contexte, historique…"><?= e($post['description'] ?? '') ?></textarea>
      </div>
      <!-- Matériaux prévus -->
      <div class="d-form-section-title">🔩 Matériaux prévus</div>
      <div id="materials-container"></div>
      <button type="button" onclick="addMaterialRow()" class="d-btn d-btn--ghost d-btn--sm" style="margin-bottom:.75rem;">➕ Ajouter un matériau</button>
      <input type="hidden" name="materials_json" id="materials_json" value="<?= e($post['materials_json'] ?? '[]') ?>">
      <!-- Champ texte libre pour compatibilité -->
      <div class="d-field" style="display:none;">
        <textarea name="materials_needed" id="materials_needed_hidden"><?= e($post['materials_needed'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════
       SECTION 4 — FINANCIER
  ══════════════════════════════════════════════ -->
  <div class="d-card d-form-section" style="margin-bottom:1.25rem;">
    <div class="d-card-head">
      <div class="d-card-title">💶 Section 4 — Financier</div>
    </div>
    <div class="d-card-body">
      <div class="d-grid-3">
        <div class="d-field">
          <label>Montant HT (€)</label>
          <input type="number" name="amount_ht" value="<?= e($post['amount_ht'] ?? '') ?>"
                 step="0.01" min="0" placeholder="0.00">
        </div>
        <div class="d-field">
          <label>Moyen de paiement</label>
          <select name="payment_method">
            <?php $pmSel = $post['payment_method'] ?? '';
            foreach ([''=>'À définir','carte'=>'Carte bancaire','espèces'=>'Espèces',
                      'virement'=>'Virement','chèque'=>'Chèque'] as $pv => $pl): ?>
              <option value="<?= e($pv) ?>" <?= $pmSel === $pv ? 'selected' : '' ?>><?= e($pl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-field" style="display:flex;align-items:center;gap:.65rem;padding-top:1.8rem;">
          <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;text-transform:none;letter-spacing:0;font-size:.9rem;color:#e8ecf5;margin-bottom:0;">
            <input type="checkbox" name="quote_accepted" value="1"
                   <?= !empty($post['quote_accepted']) ? 'checked' : '' ?>
                   style="width:auto;accent-color:#22c55e;">
            ✅ Devis accepté
          </label>
        </div>
      </div>
      <div class="d-field">
        <label>Notes internes (dispatcher)</label>
        <textarea name="notes_admin" placeholder="Notes confidentielles, instructions spéciales…" style="min-height:70px;"><?= e($post['notes_admin'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- SAVEBAR -->
  <div class="d-savebar">
    <a href="<?= e(url_for('dispatcher/interventions.php')) ?>" class="d-btn d-btn--secondary">Annuler</a>
    <button type="submit" class="d-btn d-btn--primary d-btn--lg">✅ Créer l'intervention</button>
  </div>

</form>
</div><!-- /.d-content -->

<script>
(function(){
  /* ── Bascule mode client ── */
  var rExisting = document.getElementById('r-existing');
  var rNew      = document.getElementById('r-new');
  var bExisting = document.getElementById('block-existing');
  var bNew      = document.getElementById('block-new');

  function toggleClientMode(){
    var isNew = rNew && rNew.checked;
    if(bExisting) bExisting.style.display = isNew ? 'none' : 'block';
    if(bNew)      bNew.style.display      = isNew ? 'block' : 'none';
  }
  if(rExisting) rExisting.addEventListener('change', toggleClientMode);
  if(rNew)      rNew.addEventListener('change', toggleClientMode);

  /* ── Recherche client AJAX ── */
  var searchInput  = document.getElementById('client-search');
  var dropdown     = document.getElementById('client-dropdown');
  var clientIdInp  = document.getElementById('inp-client-id');
  var selectedBox  = document.getElementById('client-selected');
  var selectedText = document.getElementById('client-selected-text');
  var clearBtn     = document.getElementById('client-clear');

  var searchTimer;
  var apiBase = <?= json_encode(url_for('dispatcher/api.php')) ?>;

  if(searchInput){
    searchInput.addEventListener('input', function(){
      var val = this.value.trim();
      clearTimeout(searchTimer);
      if(val.length < 2){ dropdown.style.display='none'; return; }
      searchTimer = setTimeout(function(){
        fetch(apiBase + '?action=search_clients&q=' + encodeURIComponent(val))
          .then(function(r){ return r.json(); })
          .then(function(data){
            if(!Array.isArray(data) || data.length === 0){
              dropdown.innerHTML = '<div style="padding:.75rem 1rem;color:#8fa0c4;font-size:.85rem;">Aucun résultat</div>';
              dropdown.style.display = 'block';
              return;
            }
            dropdown.innerHTML = data.map(function(c){
              var name = (c.lastname||'') + ' ' + (c.firstname||'');
              var sub  = [c.phone, c.city].filter(Boolean).join(' · ');
              return '<div class="client-result" data-id="'+c.id+'" data-name="'+encodeURIComponent(name.trim())+'" data-phone="'+encodeURIComponent(c.phone||'')+'" style="padding:.7rem 1rem;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.06);font-size:.87rem;">'
                + '<div style="font-weight:700;color:#e8ecf5;">'+escHtml(name.trim())+'</div>'
                + (sub ? '<div style="font-size:.77rem;color:#8fa0c4;">'+escHtml(sub)+'</div>' : '')
                + '</div>';
            }).join('');
            dropdown.querySelectorAll('.client-result').forEach(function(el){
              el.addEventListener('mouseenter', function(){ this.style.background='rgba(255,255,255,.06)'; });
              el.addEventListener('mouseleave', function(){ this.style.background=''; });
              el.addEventListener('click', function(){
                var id   = this.dataset.id;
                var name = decodeURIComponent(this.dataset.name);
                var ph   = decodeURIComponent(this.dataset.phone);
                clientIdInp.value = id;
                selectedText.textContent = name + (ph ? '  ·  ' + ph : '');
                selectedBox.style.display  = 'block';
                searchInput.style.display  = 'none';
                dropdown.style.display     = 'none';
              });
            });
            dropdown.style.display = 'block';
          })
          .catch(function(){ dropdown.style.display='none'; });
      }, 300);
    });

    document.addEventListener('click', function(e){
      if(!searchInput.contains(e.target) && !dropdown.contains(e.target)){
        dropdown.style.display='none';
      }
    });
  }

  if(clearBtn){
    clearBtn.addEventListener('click', function(){
      clientIdInp.value          = '';
      selectedBox.style.display  = 'none';
      searchInput.style.display  = 'block';
      searchInput.value          = '';
      searchInput.focus();
    });
  }

  /* ── Géocodage ── */
  var btnGeo     = document.getElementById('btn-geocode');
  var geoResult  = document.getElementById('geocode-result');
  var inpLat     = document.getElementById('inp-lat');
  var inpLng     = document.getElementById('inp-lng');

  if(btnGeo){
    btnGeo.addEventListener('click', function(){
      var addr   = (document.getElementById('new-address')     || {}).value || '';
      var postal = (document.getElementById('new-postal-code') || {}).value || '';
      var city   = (document.getElementById('new-city')        || {}).value || '';
      if(!addr && !city){ geoResult.textContent='Remplissez l\'adresse ou la ville.'; return; }
      geoResult.textContent = '⏳ Géolocalisation…';
      btnGeo.disabled = true;
      fetch(apiBase + '?action=geocode&address=' + encodeURIComponent(addr) + '&postal=' + encodeURIComponent(postal) + '&city=' + encodeURIComponent(city))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if(d.lat && d.lng){
            inpLat.value       = d.lat;
            inpLng.value       = d.lng;
            geoResult.textContent = '✅ Lat: '+d.lat+', Lng: '+d.lng;
            geoResult.style.color = '#22c55e';
          } else {
            geoResult.textContent = '⚠️ Adresse introuvable.';
            geoResult.style.color = '#f59e0b';
          }
        })
        .catch(function(){ geoResult.textContent = '❌ Erreur de géolocalisation.'; })
        .finally(function(){ btnGeo.disabled = false; });
    });
  }

  function escHtml(s){
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Presets — type d'intervention ── */
  var categorySelect   = document.getElementById('category-select');
  var typeLabelSelect  = document.getElementById('type_label_select');
  var typeLabelCustom  = document.getElementById('type_label_custom');
  var btnAddType       = document.getElementById('btn-add-type-preset');

  function loadTypePresets(cat) {
    if (!typeLabelSelect) return;
    typeLabelSelect.innerHTML = '<option value="">— Chargement… —</option>';
    if (!cat) {
      typeLabelSelect.innerHTML = '<option value="">— Choisir la catégorie d\'abord —</option>';
      return;
    }
    fetch(apiBase + '?action=get_presets&type=intervention_type&category=' + encodeURIComponent(cat))
      .then(function(r){ return r.json(); })
      .then(function(data){
        typeLabelSelect.innerHTML = '<option value="">— Choisir —</option>';
        if (Array.isArray(data) && data.length > 0) {
          data.forEach(function(p){
            var opt = document.createElement('option');
            opt.value = p.label;
            opt.textContent = p.label;
            typeLabelSelect.appendChild(opt);
          });
        }
        var otherOpt = document.createElement('option');
        otherOpt.value = '__autre__';
        otherOpt.textContent = 'Autre…';
        typeLabelSelect.appendChild(otherOpt);
      })
      .catch(function(){
        typeLabelSelect.innerHTML = '<option value="">— Erreur chargement —</option>';
      });
  }

  if (categorySelect) {
    categorySelect.addEventListener('change', function(){
      loadTypePresets(this.value);
      typeLabelCustom.style.display = 'none';
      typeLabelCustom.value = '';
    });
    // Charge au démarrage si catégorie déjà sélectionnée
    if (categorySelect.value) loadTypePresets(categorySelect.value);
  }

  if (typeLabelSelect) {
    typeLabelSelect.addEventListener('change', function(){
      if (this.value === '__autre__') {
        typeLabelCustom.style.display = 'block';
        typeLabelCustom.focus();
      } else {
        typeLabelCustom.style.display = 'none';
        typeLabelCustom.value = '';
      }
    });
  }

  if (btnAddType) {
    btnAddType.addEventListener('click', function(){
      var cat   = categorySelect ? categorySelect.value : '';
      var label = typeLabelCustom && typeLabelCustom.style.display !== 'none'
                    ? typeLabelCustom.value.trim()
                    : (typeLabelSelect ? typeLabelSelect.value : '');
      if (!label || label === '__autre__') {
        alert('Saisissez ou choisissez un type d\'intervention à ajouter.');
        return;
      }
      var fd = new FormData();
      fd.append('action', 'save_preset');
      fd.append('type', 'intervention_type');
      fd.append('label', label);
      fd.append('category', cat);
      fetch(apiBase, { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.success) {
            alert('Preset "'+label+'" ajouté.');
            if (cat) loadTypePresets(cat);
          }
        });
    });
  }

  /* ── Matériaux dynamiques ── */
  var materialsContainer = document.getElementById('materials-container');
  var materialsJsonInp   = document.getElementById('materials_json');
  var materialPresets    = [];
  var materialRows       = [];

  // Charger les presets matériaux
  fetch(apiBase + '?action=get_presets&type=material')
    .then(function(r){ return r.json(); })
    .then(function(data){ if (Array.isArray(data)) materialPresets = data; })
    .catch(function(){});

  function renderMaterialRows() {
    if (!materialsContainer) return;
    materialsContainer.innerHTML = '';
    materialRows.forEach(function(row, idx){
      var div = document.createElement('div');
      div.style.cssText = 'display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;flex-wrap:wrap;';
      // Sélect matériau
      var sel = document.createElement('select');
      sel.className = 'd-input';
      sel.style.flex = '2';
      sel.innerHTML = '<option value="">— Choisir —</option>';
      materialPresets.forEach(function(p){
        var opt = document.createElement('option');
        opt.value = p.label;
        opt.textContent = p.label;
        if (p.label === row.name) opt.selected = true;
        sel.appendChild(opt);
      });
      var otherOpt2 = document.createElement('option');
      otherOpt2.value = '__autre__';
      otherOpt2.textContent = 'Autre…';
      if (row.name && !materialPresets.find(function(p){ return p.label===row.name; })) {
        otherOpt2.selected = true;
      }
      sel.appendChild(otherOpt2);
      sel.addEventListener('change', function(){
        if (this.value !== '__autre__') {
          materialRows[idx].name = this.value;
          customInput.style.display = 'none';
          customInput.value = '';
        } else {
          customInput.style.display = 'block';
        }
        syncMaterialsJson();
      });
      // Custom input
      var customInput = document.createElement('input');
      customInput.type = 'text';
      customInput.className = 'd-input';
      customInput.placeholder = 'Nom du matériau';
      customInput.style.flex = '2';
      customInput.value = '';
      // Si le nom actuel n'est pas dans les presets, afficher le champ
      var isCustom = row.name && !materialPresets.find(function(p){ return p.label===row.name; });
      customInput.style.display = isCustom ? 'block' : 'none';
      if (isCustom) customInput.value = row.name;
      customInput.addEventListener('input', function(){
        materialRows[idx].name = this.value;
        syncMaterialsJson();
      });
      // Quantité
      var qtyInput = document.createElement('input');
      qtyInput.type = 'number';
      qtyInput.className = 'd-input';
      qtyInput.min = '0';
      qtyInput.step = '0.1';
      qtyInput.placeholder = 'Qté';
      qtyInput.style.width = '80px';
      qtyInput.value = row.qty || '';
      qtyInput.addEventListener('input', function(){
        materialRows[idx].qty = this.value;
        syncMaterialsJson();
      });
      // Unité
      var unitSel = document.createElement('select');
      unitSel.className = 'd-input';
      unitSel.style.width = '90px';
      ['pièce','m','ml','kg','L','boîte'].forEach(function(u){
        var o = document.createElement('option');
        o.value = u; o.textContent = u;
        if (u === row.unit) o.selected = true;
        unitSel.appendChild(o);
      });
      unitSel.addEventListener('change', function(){
        materialRows[idx].unit = this.value;
        syncMaterialsJson();
      });
      // Supprimer
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.textContent = '✕';
      delBtn.className = 'd-btn d-btn--ghost d-btn--sm';
      delBtn.style.cssText = 'color:#ef4444;flex-shrink:0;';
      delBtn.addEventListener('click', function(){
        materialRows.splice(idx, 1);
        renderMaterialRows();
        syncMaterialsJson();
      });
      div.appendChild(sel);
      div.appendChild(customInput);
      div.appendChild(qtyInput);
      div.appendChild(unitSel);
      div.appendChild(delBtn);
      materialsContainer.appendChild(div);
    });
  }

  function syncMaterialsJson() {
    if (!materialsJsonInp) return;
    var arr = materialRows.filter(function(r){ return r.name; });
    materialsJsonInp.value = JSON.stringify(arr);
  }

  window.addMaterialRow = function() {
    // Si les presets ne sont pas encore chargés, on attend 300ms et réessaie
    materialRows.push({ name:'', qty:'1', unit:'pièce' });
    renderMaterialRows();
    syncMaterialsJson();
  };

  // Restaurer les lignes si repopulation après erreur
  (function(){
    var existing = materialsJsonInp ? materialsJsonInp.value : '[]';
    try {
      var parsed = JSON.parse(existing);
      if (Array.isArray(parsed) && parsed.length > 0) {
        materialRows = parsed;
        // Attendre que les presets soient chargés
        setTimeout(renderMaterialRows, 400);
      }
    } catch(e){}
  })();

  /* ── Validation formulaire ── */
  var form = document.getElementById('form-new-interv');
  if(form){
    form.addEventListener('submit', function(e){
      var mode = document.querySelector('input[name="client_mode"]:checked');
      if(mode && mode.value === 'existing'){
        if(!clientIdInp.value || clientIdInp.value === '0'){
          e.preventDefault();
          alert('Veuillez sélectionner un client existant ou choisir "Nouveau client".');
        }
      }
    });
  }
})();
</script>

<?php require __DIR__.'/partials/footer.php'; ?>
