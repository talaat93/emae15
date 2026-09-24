/* EMAE Technicien — installation de l'application et notifications push. */
(function () {
  var cfg = window.TA_CFG || {};
  var hasSW = 'serviceWorker' in navigator;
  var hasPush = hasSW && 'PushManager' in window && 'Notification' in window;
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var deferredPrompt = null;
  var reg = null;

  function b64ToBytes(s) {
    var p = '='.repeat((4 - s.length % 4) % 4), raw = atob((s + p).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i); return out;
  }
  function post(action, sub) {
    var fd = new FormData();
    fd.append('csrf_token', cfg.csrf || ''); fd.append('action', action);
    if (sub) fd.append('subscription', JSON.stringify(sub));
    return fetch(cfg.pushUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function subscribe() {
    return fetch(cfg.pushUrl + '?action=key', { credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (k) {
        if (!k.key) throw new Error('clé');
        return reg.pushManager.getSubscription().then(function (s) {
          return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(k.key) });
        });
      })
      .then(function (s) { return post('subscribe', s.toJSON()); });
  }

  var box = document.getElementById('ta-install');
  function show(kind) {
    if (!box) return;
    try { if (localStorage.getItem('ta-hide-' + kind)) return; } catch (e) {}
    box.querySelectorAll('[data-kind]').forEach(function (n) { n.hidden = n.dataset.kind !== kind; });
    box.hidden = false;
    box.dataset.current = kind;
  }
  function hide() {
    if (!box) return;
    try { localStorage.setItem('ta-hide-' + box.dataset.current, '1'); } catch (e) {}
    box.hidden = true;
  }
  window.taHideInstall = hide;

  window.taEnablePush = function (btn) {
    if (!hasPush || !reg) return;
    btn.disabled = true;
    Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') { btn.disabled = false; alert('Notifications refusées. Vous pouvez les réactiver dans les réglages du téléphone.'); return; }
      return subscribe().then(function () { box.hidden = true; return post('test'); });
    }).catch(function () { btn.disabled = false; alert('Activation impossible pour le moment. Réessayez plus tard.'); });
  };
  window.taInstall = function () {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.finally(function () { deferredPrompt = null; if (box) box.hidden = true; });
  };

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferredPrompt = e;
    if (!standalone) show('android');
  });

  if (!hasSW) return;
  navigator.serviceWorker.register(cfg.swUrl, { scope: cfg.scope }).then(function (r) {
    reg = r;
    return navigator.serviceWorker.ready;
  }).then(function () {
    if (isIOS && !standalone) { show('ios'); return; }       // sur iPhone, le push exige l'app installée
    if (!hasPush) return;
    if (Notification.permission === 'granted') { subscribe().catch(function () {}); return; }  // renouvelle l'abonnement en silence
    if (Notification.permission === 'default') show('push');
  }).catch(function () {});
})();
