<?php
declare(strict_types=1);

/**
 * Édition visuelle : modifier les textes directement sur la page rendue.
 *
 * Principe : quand le mode est actif, setting() encadre les textes du
 * catalogue par des caractères de contrôle invisibles. La page se rend
 * normalement, puis un post-traitement remplace ces marqueurs par des
 * éléments cliquables. Aucune modification d'index.php n'est nécessaire.
 *
 * Les marqueurs traversent htmlspecialchars() sans dommage, et ceux qui
 * atterrissent dans une balise, un script ou un titre sont retirés au
 * lieu d'être transformés — une valeur utilisée dans un attribut ne doit
 * jamais devenir du HTML.
 */

const IE_OPEN  = "\x02";
const IE_SEP   = "\x03";
const IE_CLOSE = "\x04";

function inline_edit_active(?bool $set = null): bool
{
    static $on = false;
    if ($set !== null) $on = $set;
    return $on;
}

/**
 * Encadre une valeur de marqueurs si le champ est éditable en place.
 * Appelée par setting() à chaque lecture : reste sans effet hors mode édition.
 */
function inline_edit_wrap(string $key, string $value): string
{
    if (!inline_edit_active() || $value === '' || str_contains($value, IE_OPEN)) return $value;
    if (!isset(admin_inline_keys()[$key])) return $value;
    return IE_OPEN.$key.IE_SEP.$value.IE_CLOSE;
}

/**
 * Retire tous les marqueurs d'un fragment, en ne gardant que les valeurs.
 * Traite aussi leur forme échappée, produite quand une valeur traverse
 * json_encode() — cas des données structurées destinées à Google.
 */
function inline_edit_strip(string $s): string
{
    $s = preg_replace('/'.IE_OPEN.'[a-z0-9_]+'.IE_SEP.'(.*?)'.IE_CLOSE.'/su', '$1', $s) ?? $s;
    return preg_replace('/\\\\u0002[a-z0-9_]+\\\\u0003(.*?)\\\\u0004/su', '$1', $s) ?? $s;
}

/** Étiquette lisible d'un champ : « Nos services — Phrase d'accroche ». */
function inline_edit_labels(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (admin_page_catalog() as $page) {
        foreach ($page['sections'] as $s) {
            foreach ($s['fields'] as $f) {
                if (!admin_field_is_inline($f)) continue;
                $map[$f['key']] = [
                    'l' => $s['label'].' — '.$f['label'],
                    'd' => admin_field_raw($f) === '',              // encore au texte par défaut
                    'z' => zone_ctx_id() > 0 && !setting_is_inherited($f['key']), // propre à la zone
                ];
            }
        }
    }
    return $map;
}

/**
 * Transforme la page rendue : marqueurs → éléments éditables, puis
 * injection de la barre d'outils. Appelé par ob_start(), donc exécuté
 * même quand index.php se termine par exit.
 */
function inline_edit_postprocess(string $html): string
{
    if (!inline_edit_active()) return inline_edit_strip($html);
    if (stripos($html, '</body>') === false) return inline_edit_strip($html);

    $labels = inline_edit_labels();

    // On découpe en alternant texte et balises pour ne transformer que le texte visible.
    $parts  = preg_split('/(<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $skip   = 0;                    // profondeur dans script/style/title/textarea
    $out    = '';
    $count  = 0;

    foreach ($parts as $part) {
        if ($part === '') continue;
        if ($part[0] === '<') {
            if (preg_match('/^<\s*(script|style|title|textarea)\b/i', $part))  $skip++;
            elseif (preg_match('/^<\s*\/\s*(script|style|title|textarea)/i', $part) && $skip > 0) $skip--;
            $out .= inline_edit_strip($part);   // jamais de balise dans un attribut
            continue;
        }
        if ($skip > 0) { $out .= inline_edit_strip($part); continue; }

        $out .= preg_replace_callback(
            '/'.IE_OPEN.'([a-z0-9_]+)'.IE_SEP.'(.*?)'.IE_CLOSE.'/su',
            static function (array $m) use ($labels, &$count): string {
                $key = $m[1];
                $val = $m[2];
                if (!isset($labels[$key])) return $val;
                $count++;
                $meta = $labels[$key];
                $cls  = 'ie';
                if ($meta['d']) $cls .= ' ie--def';
                if ($meta['z']) $cls .= ' ie--zone';
                return '<span class="'.$cls.'" data-k="'.htmlspecialchars($key, ENT_QUOTES).'"'
                     . ' data-l="'.htmlspecialchars($meta['l'], ENT_QUOTES).'">'.$val.'</span>';
            },
            $part
        ) ?? $part;
    }

    // Filet de sécurité : plus aucun marqueur ne doit survivre, sous quelque forme que ce soit.
    $out = inline_edit_strip($out);

    return str_ireplace('</body>', inline_edit_toolbar($count).'</body>', $out);
}

/** Barre d'outils et comportement d'édition, injectés en bas de page. */
function inline_edit_toolbar(int $count): string
{
    $zone     = zone_ctx_name();
    $zoneSlug = htmlspecialchars(zone_ctx_slug(), ENT_QUOTES);
    $zoneHtml = $zone !== '' ? '📍 '.htmlspecialchars($zone, ENT_QUOTES) : '🌐 Site global';
    $back     = htmlspecialchars(url_for('admin/index.php'), ENT_QUOTES);
    $endpoint = htmlspecialchars(url_for('admin/inline_save.php'), ENT_QUOTES);
    $csrf     = htmlspecialchars(csrf_token(), ENT_QUOTES);
    $hint     = $zone !== ''
        ? 'Vous modifiez uniquement cette zone. Vider un texte le fait revenir à celui du site global.'
        : 'Vous modifiez le site global. Vider un texte le fait revenir au texte d\'origine.';

    return <<<HTML
<style>
.ie{outline:1px dashed rgba(47,102,210,.35);outline-offset:2px;border-radius:3px;cursor:text;transition:outline-color .12s,background .12s;}
.ie:hover{outline:2px solid #2f66d2;background:rgba(47,102,210,.07);}
.ie:focus{outline:2px solid #2f66d2;background:rgba(47,102,210,.10);}
.ie--def{outline-color:rgba(240,123,29,.45);outline-style:dotted;}
.ie--def:hover{outline-color:#F07B1D;background:rgba(240,123,29,.09);}
.ie--zone{outline-color:rgba(22,163,74,.5);}
.ie--zone:hover{outline-color:#16a34a;background:rgba(22,163,74,.09);}
.ie--dirty{outline:2px solid #F07B1D !important;background:rgba(240,123,29,.16) !important;}
body.ie-quiet .ie{outline-color:transparent;}
#ie-tip{position:fixed;z-index:2147483646;background:#1b2d6b;color:#fff;font:600 11px/1.3 system-ui,sans-serif;
  padding:.3rem .5rem;border-radius:6px;pointer-events:none;opacity:0;transition:opacity .12s;max-width:260px;}
#ie-bar{position:fixed;left:0;right:0;bottom:0;z-index:2147483647;background:#12204d;color:#fff;
  font:500 13px/1.4 system-ui,-apple-system,sans-serif;padding:.6rem .8rem;
  display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;box-shadow:0 -6px 22px rgba(0,0,0,.28);}
#ie-bar a,#ie-bar button{font:inherit;border-radius:9px;padding:.42rem .8rem;cursor:pointer;border:1px solid rgba(255,255,255,.28);
  background:rgba(255,255,255,.08);color:#fff;text-decoration:none;white-space:nowrap;}
#ie-bar a:hover,#ie-bar button:hover{background:rgba(255,255,255,.18);}
#ie-bar .ie-zone{font-weight:800;}
#ie-bar .ie-hint{opacity:.72;font-size:11.5px;flex:1 1 240px;min-width:0;}
#ie-save{background:#F07B1D !important;border-color:#F07B1D !important;font-weight:800 !important;}
#ie-save[disabled]{opacity:.4;cursor:default;}
#ie-count{font-weight:800;color:#ffd9b0;}
#ie-legend{display:flex;gap:.7rem;flex-wrap:wrap;font-size:11px;opacity:.8;width:100%;margin-top:-.1rem;}
#ie-legend i{font-style:normal;display:inline-flex;align-items:center;gap:.3rem;}
#ie-legend b{width:12px;height:12px;border-radius:3px;display:inline-block;}
body{padding-bottom:96px !important;}
</style>
<div id="ie-tip"></div>
<div id="ie-bar">
  <a href="{$back}">← Admin</a>
  <span class="ie-zone">{$zoneHtml}</span>
  <span class="ie-hint">{$hint}</span>
  <span id="ie-count"></span>
  <button type="button" id="ie-toggle">Masquer les repères</button>
  <button type="button" id="ie-cancel">Annuler</button>
  <button type="button" id="ie-save" disabled>Enregistrer</button>
  <div id="ie-legend">
    <i><b style="outline:2px dotted #F07B1D;outline-offset:-2px;"></b> texte d'origine, jamais personnalisé</i>
    <i><b style="outline:2px solid #16a34a;outline-offset:-2px;"></b> propre à cette zone</i>
    <i><b style="outline:2px solid #2f66d2;outline-offset:-2px;"></b> personnalisé</i>
    <i>Ce qui n'est pas encadré se modifie depuis l'administration ({$count} textes éditables ici).</i>
  </div>
</div>
<script>
(function(){
  var fields = Array.prototype.slice.call(document.querySelectorAll('.ie'));
  if (!fields.length) return;
  var original = {}, dirty = {}, tip = document.getElementById('ie-tip'),
      save = document.getElementById('ie-save'), count = document.getElementById('ie-count');

  fields.forEach(function(el){
    var k = el.dataset.k;
    if (!(k in original)) original[k] = el.textContent;
    el.setAttribute('contenteditable', 'plaintext-only');
    el.spellcheck = false;
  });

  function refresh(){
    var n = Object.keys(dirty).length;
    count.textContent = n ? n + (n > 1 ? ' modifications non enregistrées' : ' modification non enregistrée') : '';
    save.disabled = n === 0;
  }
  function sameKey(k){ return fields.filter(function(el){ return el.dataset.k === k; }); }

  fields.forEach(function(el){
    el.addEventListener('input', function(){
      var k = el.dataset.k, v = el.textContent;
      // un même texte peut apparaître à plusieurs endroits : on les garde alignés
      sameKey(k).forEach(function(o){ if (o !== el && o.textContent !== v) o.textContent = v; });
      if (v === original[k]) { delete dirty[k]; sameKey(k).forEach(function(o){ o.classList.remove('ie--dirty'); }); }
      else { dirty[k] = v; sameKey(k).forEach(function(o){ o.classList.add('ie--dirty'); }); }
      refresh();
    });
    // Entrée valide au lieu d'insérer un saut de ligne
    el.addEventListener('keydown', function(ev){
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); el.blur(); }
      if (ev.key === 'Escape') { el.textContent = original[el.dataset.k]; el.dispatchEvent(new Event('input')); el.blur(); }
    });
    el.addEventListener('mouseenter', function(){
      tip.textContent = el.dataset.l;
      var r = el.getBoundingClientRect();
      tip.style.left = Math.max(6, Math.min(r.left, window.innerWidth - 270)) + 'px';
      tip.style.top  = (r.top > 34 ? r.top - 30 : r.bottom + 8) + 'px';
      tip.style.opacity = '1';
    });
    el.addEventListener('mouseleave', function(){ tip.style.opacity = '0'; });
  });

  // Ne pas suivre les liens quand on clique pour écrire dedans
  document.addEventListener('click', function(ev){
    if (ev.target.closest && ev.target.closest('.ie') && ev.target.closest('a')) ev.preventDefault();
  }, true);

  document.getElementById('ie-toggle').addEventListener('click', function(){
    var quiet = document.body.classList.toggle('ie-quiet');
    this.textContent = quiet ? 'Afficher les repères' : 'Masquer les repères';
  });

  document.getElementById('ie-cancel').addEventListener('click', function(){
    if (!Object.keys(dirty).length) return;
    if (!confirm('Abandonner les modifications non enregistrées ?')) return;
    fields.forEach(function(el){ el.textContent = original[el.dataset.k]; el.classList.remove('ie--dirty'); });
    dirty = {}; refresh();
  });

  save.addEventListener('click', function(){
    if (!Object.keys(dirty).length) return;
    save.disabled = true; save.textContent = 'Enregistrement…';
    fetch('{$endpoint}', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({csrf_token: '{$csrf}', zone: '{$zoneSlug}', fields: dirty})
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (!res || !res.ok) throw new Error((res && res.error) || 'refus du serveur');
      Object.keys(dirty).forEach(function(k){
        original[k] = dirty[k];
        sameKey(k).forEach(function(o){ o.classList.remove('ie--dirty'); });
      });
      dirty = {}; refresh();
      save.textContent = '✓ Enregistré';
      setTimeout(function(){ save.textContent = 'Enregistrer'; location.reload(); }, 700);
    })
    .catch(function(err){
      alert("L'enregistrement a échoué : " + err.message);
      save.textContent = 'Enregistrer'; save.disabled = false;
    });
  });

  window.addEventListener('beforeunload', function(ev){
    if (Object.keys(dirty).length) { ev.preventDefault(); ev.returnValue = ''; }
  });

  refresh();
})();
</script>
HTML;
}
