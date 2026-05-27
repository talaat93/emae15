<?php
declare(strict_types=1);
$pageTitle = 'Carte GPS';
$dispSection = 'map';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
#map { flex: 1; min-height: 0; background: #0f1e3d; }
.map-panel { width: 300px; flex-shrink: 0; display: flex; flex-direction: column; background: rgba(255,255,255,.03); border-right: 1px solid rgba(255,255,255,.08); overflow: hidden; }
.map-panel-head { padding: .85rem 1rem; border-bottom: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
.map-panel-list { flex: 1; overflow-y: auto; }
.map-list-item { padding: .7rem 1rem; border-bottom: 1px solid rgba(255,255,255,.05); cursor: pointer; transition: background .15s; }
.map-list-item:hover { background: rgba(255,255,255,.05); }
.map-list-item.active { background: rgba(238,125,26,.1); border-left: 3px solid #ee7d1a; }
.map-list-title { font-size: .8rem; font-weight: 700; color: #e8ecf5; }
.map-list-meta { font-size: .72rem; color: #8fa0c4; margin-top: .15rem; }
.map-filter-bar { display: flex; gap: .5rem; flex-wrap: wrap; padding: .75rem 1rem; border-bottom: 1px solid rgba(255,255,255,.08); background: rgba(0,0,0,.15); flex-shrink: 0; }
.map-filter-select { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); color: #e8ecf5; font-size: .75rem; padding: .35rem .65rem; border-radius: 6px; outline: none; cursor: pointer; }
.map-filter-select option { background: #0f1e3d; color: #e8ecf5; }
.leaflet-popup-content-wrapper { background: #0f1e3d; color: #e8ecf5; border: 1px solid rgba(255,255,255,.15); border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.5); }
.leaflet-popup-tip { background: #0f1e3d; }
.leaflet-popup-content { margin: 1rem; font-family: "Outfit", Arial, sans-serif; font-size: .85rem; min-width: 200px; }
.popup-title { font-weight: 700; color: #e8ecf5; font-size: .92rem; margin-bottom: .5rem; }
.popup-row { display: flex; gap: .5rem; align-items: flex-start; margin-bottom: .3rem; font-size: .8rem; color: #8fa0c4; }
.popup-row span:last-child { color: #e8ecf5; }
.popup-link { display: inline-block; margin-top: .65rem; background: rgba(238,125,26,.2); color: #ee7d1a; border: 1px solid rgba(238,125,26,.4); border-radius: 6px; padding: .35rem .85rem; font-size: .78rem; font-weight: 700; text-decoration: none; }
.popup-link:hover { background: rgba(238,125,26,.35); }
.marker-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(255,255,255,.6); box-shadow: 0 2px 6px rgba(0,0,0,.4); }
.marker-urgent { animation: pulse-map 1.5s infinite; }
@keyframes pulse-map {
  0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.6); }
  50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}
.map-counter { font-size: .78rem; font-weight: 600; color: #8fa0c4; }
</style>';
require __DIR__.'/partials/header.php';

$statusConfig   = intervention_status_config();
$categoryConfig = intervention_category_config();
$technicians    = all_technicians();
?>

<div class="d-topbar">
  <button class="d-menu-toggle" id="d-menu-toggle" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="d-topbar-title">
    <span class="d-topbar-ico">🗺️</span> Carte GPS des interventions
  </div>
  <div class="d-topbar-actions" style="display:flex;gap:.5rem;align-items:center;">
    <span id="marker-count" class="map-counter">Chargement…</span>
    <a href="<?= e(url_for('dispatcher/intervention_new.php')) ?>" class="d-btn d-btn-primary d-btn-sm">+ Nouvelle</a>
  </div>
</div>

<!-- Barre de filtres -->
<div class="map-filter-bar">
  <select class="map-filter-select" id="filter-status" onchange="applyFilters()">
    <option value="">Tous les statuts</option>
    <?php foreach ($statusConfig as $key => $cfg): ?>
    <option value="<?= e($key) ?>"><?= e($cfg['label']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="map-filter-select" id="filter-category" onchange="applyFilters()">
    <option value="">Toutes catégories</option>
    <?php foreach ($categoryConfig as $key => $cfg): ?>
    <option value="<?= e($key) ?>"><?= e($cfg['icon'].' '.$cfg['label']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="map-filter-select" id="filter-tech" onchange="applyFilters()">
    <option value="">Tous les techniciens</option>
    <?php foreach ($technicians as $tech): ?>
    <option value="<?= (int)$tech['id'] ?>"><?= e($tech['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label style="display:flex;align-items:center;gap:.35rem;font-size:.75rem;color:#8fa0c4;cursor:pointer;">
    <input type="checkbox" id="filter-urgent" onchange="applyFilters()" style="accent-color:#ef4444;"> Urgences seulement
  </label>
  <button onclick="resetFilters()" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#8fa0c4;font-size:.72rem;padding:.3rem .7rem;border-radius:6px;cursor:pointer;">✕ Réinitialiser</button>
</div>

<div class="d-content" style="padding:0;flex:1;">
  <div style="display:flex;height:calc(100vh - 56px - 56px);">

    <!-- Panneau gauche : liste -->
    <div class="map-panel">
      <div class="map-panel-head">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8fa0c4;">Interventions géolocalisées</div>
        <div style="font-size:.85rem;font-weight:700;color:#e8ecf5;margin-top:.2rem;" id="panel-count">—</div>
      </div>
      <div class="map-panel-list" id="panel-list">
        <div style="padding:2rem 1rem;text-align:center;color:#8fa0c4;font-size:.82rem;">
          <div style="font-size:1.8rem;margin-bottom:.5rem;">🗺️</div>
          Chargement…
        </div>
      </div>
    </div>

    <!-- Carte Leaflet -->
    <div id="map"></div>

  </div>
</div>

<script>
var statusConfig = <?= json_encode(array_map(fn($c) => ['label'=>$c['label'],'color'=>$c['color'],'bg'=>$c['bg']], $statusConfig), JSON_UNESCAPED_SLASHES) ?>;
var categoryConfig = <?= json_encode(array_map(fn($c) => ['label'=>$c['label'],'icon'=>$c['icon'],'color'=>$c['color']], $categoryConfig), JSON_UNESCAPED_SLASHES) ?>;

// Init map
var map = L.map('map', { center: [46.2276, 2.2137], zoom: 6, zoomControl: true });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 19
}).addTo(map);

var allMarkers = [];
var markerObjects = {};
var markerLayer = L.layerGroup().addTo(map);

function getStatusColor(status) {
  return (statusConfig[status] && statusConfig[status].color) ? statusConfig[status].color : '#8fa0c4';
}
function getCategoryColor(cat) {
  return (categoryConfig[cat] && categoryConfig[cat].color) ? categoryConfig[cat].color : '#8fa0c4';
}

function createMarkerIcon(color, urgent) {
  var cls = urgent ? 'marker-dot marker-urgent' : 'marker-dot';
  var html = '<div class="' + cls + '" style="background:' + color + ';border-color:rgba(255,255,255,.8);"></div>';
  return L.divIcon({ html: html, className: '', iconSize: [14, 14], iconAnchor: [7, 7], popupAnchor: [0, -10] });
}

function buildPopup(m) {
  var stLabel = (statusConfig[m.status] && statusConfig[m.status].label) ? statusConfig[m.status].label : m.status;
  var stColor = getStatusColor(m.status);
  var catLabel = (categoryConfig[m.category] && categoryConfig[m.category].label) ? categoryConfig[m.category].icon + ' ' + categoryConfig[m.category].label : m.category;
  var html = '<div class="popup-title">' + escHtml(m.client) + (m.urgency ? ' <span style="color:#ef4444;font-size:.75rem;">🚨 URGENT</span>' : '') + '</div>';
  html += '<div class="popup-row"><span>📍</span><span>' + escHtml(m.address || '') + (m.city ? ', ' + escHtml(m.city) : '') + '</span></div>';
  html += '<div class="popup-row"><span>Statut:</span><span style="color:' + stColor + ';font-weight:700;">' + escHtml(stLabel) + '</span></div>';
  html += '<div class="popup-row"><span>Catégorie:</span><span>' + escHtml(catLabel) + '</span></div>';
  if (m.tech_name) html += '<div class="popup-row"><span>👷</span><span>' + escHtml(m.tech_name) + '</span></div>';
  if (m.ref) html += '<div class="popup-row"><span>Réf:</span><span>' + escHtml(m.ref) + '</span></div>';
  html += '<a href="<?= e(url_for('dispatcher/intervention_view.php')) ?>?id=' + m.id + '" class="popup-link">Voir la fiche →</a>';
  return html;
}

function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function loadMarkers() {
  fetch('<?= e(url_for('dispatcher/api.php')) ?>?action=map_markers')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      allMarkers = Array.isArray(data) ? data : [];
      renderMarkers(allMarkers);
    })
    .catch(function(e){ console.error(e); });
}

function renderMarkers(markers) {
  markerLayer.clearLayers();
  markerObjects = {};
  document.getElementById('panel-list').innerHTML = '';
  document.getElementById('panel-count').textContent = markers.length + ' intervention' + (markers.length > 1 ? 's' : '');
  document.getElementById('marker-count').textContent = markers.length + ' marqueur' + (markers.length > 1 ? 's' : '');

  if (markers.length === 0) {
    document.getElementById('panel-list').innerHTML = '<div style="padding:2rem 1rem;text-align:center;color:#8fa0c4;font-size:.82rem;"><div style="font-size:1.8rem;margin-bottom:.5rem;">📍</div>Aucune intervention géolocalisée</div>';
    return;
  }

  markers.forEach(function(m) {
    var color = getCategoryColor(m.category);
    var icon  = createMarkerIcon(color, !!m.urgency);
    var mk = L.marker([m.lat, m.lng], { icon: icon });
    mk.bindPopup(buildPopup(m), { maxWidth: 280 });
    mk.addTo(markerLayer);
    markerObjects[m.id] = mk;

    // Panel item
    var stColor = getStatusColor(m.status);
    var catLabel = (categoryConfig[m.category]) ? categoryConfig[m.category].icon + ' ' + categoryConfig[m.category].label : m.category;
    var stLabel = (statusConfig[m.status]) ? statusConfig[m.status].label : m.status;
    var clientName = escHtml(m.client || 'Client #' + m.id);
    var item = document.createElement('div');
    item.className = 'map-list-item';
    item.dataset.id = m.id;
    item.innerHTML =
      '<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem;">' +
        '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + color + ';flex-shrink:0;' + (m.urgency ? 'box-shadow:0 0 0 2px rgba(239,68,68,.5);' : '') + '"></span>' +
        '<div class="map-list-title">' + clientName + (m.urgency ? ' <span style="color:#ef4444;font-size:.68rem;">URGENT</span>' : '') + '</div>' +
      '</div>' +
      '<div class="map-list-meta">' + escHtml(catLabel) + ' · <span style="color:' + stColor + ';font-weight:700;">' + escHtml(stLabel) + '</span></div>' +
      (m.city ? '<div class="map-list-meta">📍 ' + escHtml(m.city) + '</div>' : '');
    item.addEventListener('click', function() {
      document.querySelectorAll('.map-list-item').forEach(function(el){ el.classList.remove('active'); });
      item.classList.add('active');
      map.setView([m.lat, m.lng], 14, { animate: true });
      markerObjects[m.id].openPopup();
    });
    document.getElementById('panel-list').appendChild(item);
  });
}

function applyFilters() {
  var fStatus   = document.getElementById('filter-status').value;
  var fCategory = document.getElementById('filter-category').value;
  var fTech     = document.getElementById('filter-tech').value;
  var fUrgent   = document.getElementById('filter-urgent').checked;
  var filtered  = allMarkers.filter(function(m) {
    if (fStatus   && m.status   !== fStatus)              return false;
    if (fCategory && m.category !== fCategory)            return false;
    if (fTech     && String(m.tech_id) !== String(fTech)) return false;
    if (fUrgent   && !m.urgency)                          return false;
    return true;
  });
  renderMarkers(filtered);
}

function resetFilters() {
  document.getElementById('filter-status').value = '';
  document.getElementById('filter-category').value = '';
  document.getElementById('filter-tech').value = '';
  document.getElementById('filter-urgent').checked = false;
  renderMarkers(allMarkers);
}

loadMarkers();
</script>

<?php require __DIR__.'/partials/footer.php'; ?>
