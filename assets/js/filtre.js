/* EMAE — Rechercher au lieu de défiler.
 *
 * 1. Listes : une page qui déclare data-filtre sur <body> reçoit une case « Rechercher »
 *    en haut de son contenu. Chaque élément de la liste (data-filtre-items, par défaut les
 *    lignes des tableaux) est masqué s'il ne contient pas tous les mots tapés, sans tenir
 *    compte des majuscules ni des accents. Les blocs devenus vides (data-filtre-groupes)
 *    sont masqués aussi. Rien n'est envoyé au serveur.
 * 2. Menus déroulants : tout <select> de plus de 15 choix (ou marqué data-recherche)
 *    reçoit une petite case « Rechercher » qui réduit la liste des choix.
 *    window.emaeSelectRecherche(select) le fait pour un menu créé après coup.
 */
(function () {
  'use strict';
  var norm = function (s) {
    return (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  };
  // Texte d'un élément, valeurs des champs comprises (tarifs modifiables sur place).
  var texte = function (el) {
    var t = el.textContent || '';
    el.querySelectorAll('input:not([type=hidden]):not([type=checkbox]), select, textarea').forEach(function (f) {
      t += ' ' + (f.tagName === 'SELECT' ? (f.options[f.selectedIndex] || {}).text || '' : f.value);
    });
    return norm(t);
  };

  /* ── 1. Recherche dans les listes ── */
  function listes() {
    var body = document.body;
    if (!body || !body.hasAttribute('data-filtre')) return;
    var selItems = body.getAttribute('data-filtre-items') || '.d-table tbody tr';
    var selGroupes = body.getAttribute('data-filtre-groupes') || '.d-card';
    var cible = document.querySelector(body.getAttribute('data-filtre-cible') || '.d-content');
    if (!cible) return;
    // Éléments relevés une fois au chargement ; masqués par une classe (le style d'origine reste intact).
    var liste = Array.prototype.slice.call(document.querySelectorAll(selItems));
    if (liste.length < 6) return;   // liste courte : inutile

    var box = document.createElement('div');
    box.className = 'emae-filtre';
    box.innerHTML = '<input type="search" placeholder="Rechercher dans la liste…" aria-label="Rechercher dans la liste" autocomplete="off">'
      + '<span class="emae-filtre-n" aria-live="polite"></span>';
    cible.insertBefore(box, cible.firstChild);
    var input = box.querySelector('input'), compteur = box.querySelector('.emae-filtre-n');

    var appliquer = function () {
      var mots = norm(input.value).split(' ').filter(Boolean);
      var vus = 0;
      liste.forEach(function (el) {
        var ok = !mots.length || mots.every(function (m) { return texte(el).indexOf(m) >= 0; });
        el.classList.toggle('emae-cache', !ok);
        if (ok) vus++;
      });
      // Blocs sans aucun élément visible : masqués (seulement s'ils contiennent des éléments de la liste).
      document.querySelectorAll(selGroupes).forEach(function (g) {
        var dedans = liste.filter(function (el) { return g.contains(el); });
        if (!dedans.length || g.contains(box)) return;
        g.classList.toggle('emae-cache', !!mots.length && !dedans.some(function (el) { return !el.classList.contains('emae-cache'); }));
      });
      compteur.textContent = mots.length ? vus + ' / ' + liste.length : '';
    };
    input.addEventListener('input', appliquer);
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { input.value = ''; appliquer(); } });
  }

  /* ── 2. Recherche dans les menus déroulants longs ── */
  function selectRecherche(sel) {
    if (!sel || sel.dataset.rechercheOk || sel.multiple) return;
    sel.dataset.rechercheOk = '1';
    var modele = sel.cloneNode(true);   // tous les choix d'origine, groupes compris
    var champ = document.createElement('input');
    champ.type = 'search';
    champ.className = 'emae-select-recherche';
    champ.placeholder = 'Rechercher…';
    champ.setAttribute('aria-label', 'Rechercher dans la liste des choix');
    champ.autocomplete = 'off';
    sel.parentNode.insertBefore(champ, sel);
    champ.addEventListener('input', function () {
      var mots = norm(champ.value).split(' ').filter(Boolean);
      var choisi = sel.value;
      var copie = modele.cloneNode(true);
      copie.querySelectorAll('option').forEach(function (o) {
        o.selected = o.value === choisi;
        var ok = !mots.length || o.value === '' || o.value === choisi || mots.every(function (m) { return norm(o.text).indexOf(m) >= 0; });
        if (!ok) o.remove();
      });
      copie.querySelectorAll('optgroup').forEach(function (g) { if (!g.querySelector('option')) g.remove(); });
      sel.innerHTML = copie.innerHTML;
      sel.value = choisi;
      // Un seul résultat (en plus du choix vide) : il est choisi directement.
      var vrais = Array.prototype.filter.call(sel.options, function (o) { return o.value !== ''; });
      if (mots.length && vrais.length === 1 && vrais[0].value !== choisi) { sel.value = vrais[0].value; sel.dispatchEvent(new Event('change', { bubbles: true })); }
    });
  }
  window.emaeSelectRecherche = selectRecherche;

  function menus() {
    document.querySelectorAll('select').forEach(function (s) {
      if (s.hasAttribute('data-recherche') || s.options.length > 15) selectRecherche(s);
    });
  }

  var style = document.createElement('style');
  style.textContent = '.emae-cache{display:none!important}.emae-filtre{display:flex;align-items:center;gap:.6rem;margin:0 0 1rem}'
    + '.emae-filtre input{flex:1;max-width:460px;padding:.55rem .8rem;border:1px solid #d5dce6;border-radius:8px;font:inherit;font-size:.92rem;background:#fff}'
    + '.emae-filtre-n{font-size:.8rem;color:#8c99ad}'
    + '.emae-select-recherche{display:block;width:100%;margin:0 0 .3rem;padding:.35rem .55rem;border:1px solid #d5dce6;border-radius:6px;font:inherit;font-size:.82rem;background:#fff}';
  document.head.appendChild(style);

  var go = function () { listes(); menus(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
