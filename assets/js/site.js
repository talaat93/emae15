document.addEventListener('DOMContentLoaded', function () {
  /* Nav mobile */
  var toggle = document.querySelector('.nav-toggle');
  var nav    = document.querySelector('.site-nav');
  if (toggle && nav) {
    /* Hauteur exacte du panneau : de la base de l'en-tête (variable : il passe
       sur deux lignes sur les petits écrans) jusqu'au-dessus de la barre CTA. */
    var sizeNav = function () {
      if (!nav.classList.contains('open')) { nav.style.maxHeight = ''; return; }
      if (getComputedStyle(nav).position !== 'absolute') { nav.style.maxHeight = ''; return; }
      var header = document.querySelector('.site-header');
      var bar    = document.querySelector('.mob-bar');
      var barH   = (bar && getComputedStyle(bar).display !== 'none') ? bar.offsetHeight : 0;
      var top    = header ? header.getBoundingClientRect().bottom : 0;
      nav.style.maxHeight = Math.max(160, window.innerHeight - top - barH) + 'px';
    };
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      nav.classList.toggle('open');
      var isOpen = nav.classList.contains('open');
      document.body.style.overflow = isOpen ? 'hidden' : '';
      document.body.classList.toggle('nav-open', isOpen);
      sizeNav();
    });
    window.addEventListener('resize', sizeNav);
    window.addEventListener('orientationchange', sizeNav);
    nav.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', function(){
        nav.classList.remove('open');
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded','false');
        document.body.style.overflow='';
        nav.style.maxHeight='';
      });
    });
  }

  /* Smooth scroll */
  document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click',function(e){
      var t=document.querySelector(a.getAttribute('href'));
      if(t){e.preventDefault();t.scrollIntoView({behavior:'smooth',block:'start'});}
    });
  });

  /* FAQ toggle */
  document.querySelectorAll('.faq-item').forEach(function(item){
    var q=item.querySelector('.faq-q');
    var a=item.querySelector('.faq-a');
    if(!q||!a)return;
    q.addEventListener('click',function(){
      var open=item.classList.toggle('open');
      a.classList.toggle('show',open);
    });
  });

  /* FAQ catégories */
  var cats=document.querySelectorAll('[data-cat]');
  var groups=document.querySelectorAll('[data-group]');
  cats.forEach(function(btn){
    btn.addEventListener('click',function(){
      cats.forEach(function(b){b.classList.remove('active');});
      btn.classList.add('active');
      var active=btn.dataset.cat;
      groups.forEach(function(g){g.style.display=(active==='all'||g.dataset.group===active)?'':'none';});
    });
  });

  /* Tracking Google Ads — fire all conversion labels */
  function gads_fire(labels){
    if(typeof gtag==='undefined'||!window._gAdsId||!labels||!labels.length)return;
    labels.forEach(function(lbl){
      gtag('event','conversion',{'send_to':window._gAdsId+'/'+lbl});
    });
  }

  /* Appels téléphoniques */
  document.querySelectorAll('a[href^="tel:"]').forEach(function(l){
    l.addEventListener('click',function(){
      gads_fire(window._gAdsCv||[]);
      if(typeof gtag!=='undefined') gtag('event','phone_call',{'event_category':'contact'});
    });
  });

  /* Formulaires — la conversion Google Ads « Demande de devis » est envoyée
     par render_head() une fois l'envoi enregistré, pas ici. */
  document.querySelectorAll('form').forEach(function(f){
    f.addEventListener('submit',function(){
      if(typeof gtag!=='undefined') gtag('event','generate_lead',{'event_category':'lead','event_label':'form_submit'});
    });
  });

  /* ── Chatbot widget ── */
  (function(){
    var widget   = document.getElementById('chat-widget');
    if (!widget) return;
    var btn      = document.getElementById('chat-btn');
    var box      = document.getElementById('chat-box');
    var closeB   = document.getElementById('chat-close');
    var msgs     = document.getElementById('chat-msgs');
    var input    = document.getElementById('chat-input');
    var send     = document.getElementById('chat-send');
    var endpoint = widget.dataset.endpoint || '/api/chat.php';
    var welcome  = widget.dataset.welcome  || 'Bonjour ! Comment puis-je vous aider ?';
    var history  = [];
    var opened   = false;

    function addMsg(text, role) {
      var el = document.createElement('div');
      el.className = 'chat-msg ' + role;
      el.textContent = text;
      msgs.appendChild(el);
      msgs.scrollTop = msgs.scrollHeight;
      return el;
    }

    function openChat() {
      box.classList.add('open');
      if (!opened) { opened = true; addMsg(welcome, 'bot'); }
      setTimeout(function(){ input.focus(); }, 50);
    }

    btn.addEventListener('click', function(){
      box.classList.contains('open') ? box.classList.remove('open') : openChat();
    });
    closeB.addEventListener('click', function(){ box.classList.remove('open'); });

    function doSend() {
      var msg = input.value.trim();
      if (!msg || send.disabled) return;
      input.value = '';
      addMsg(msg, 'user');
      var typing = addMsg('…', 'bot typing');
      send.disabled = true;
      history.push({role:'user', content:msg});

      fetch(endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({message: msg, history: history.slice(0, -1)})
      })
      .then(function(r){ return r.json(); })
      .then(function(d){
        typing.remove();
        var reply = d.reply || 'Désolé, une erreur est survenue.';
        addMsg(reply, 'bot');
        history.push({role:'assistant', content:reply});
      })
      .catch(function(){
        typing.remove();
        addMsg('Erreur de connexion. Appelez-nous directement.', 'bot');
      })
      .finally(function(){ send.disabled = false; input.focus(); });
    }

    send.addEventListener('click', doSend);
    input.addEventListener('keydown', function(e){
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); }
    });
  })();

  // WhatsApp tracking
  var waBtn = document.querySelector('.wa-btn');
  if (waBtn) {
    waBtn.addEventListener('click', function () {
      if (typeof gtag !== 'undefined') gtag('event', 'whatsapp_click', {'event_category': 'contact'});
      gads_fire(window._gAdsCv || []);
    });
  }

  // Popup urgence
  (function () {
    var popup = document.getElementById('urgency-popup');
    if (!popup) return;
    if (sessionStorage.getItem('urgency_shown')) return;
    var delay = parseInt(popup.dataset.delay || '15', 10) * 1000;
    setTimeout(function () {
      popup.classList.add('show');
      sessionStorage.setItem('urgency_shown', '1');
    }, delay);
    function closePopup() { popup.classList.remove('show'); }
    var closeBtn = document.getElementById('urgency-close');
    var overlay  = document.getElementById('urgency-overlay');
    if (closeBtn) closeBtn.addEventListener('click', closePopup);
    if (overlay)  overlay.addEventListener('click', closePopup);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePopup(); });
  })();

  /* ── Scroll Reveal — IntersectionObserver ── */
  (function () {
    var targets = document.querySelectorAll(
      '.svc-card, .why-card, .process-step, .review-card, ' +
      '.zone-card, .real-card, .interv-card, .step-card'
    );
    if (!targets.length || !('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('reveal', 'visible'); });
      return;
    }
    targets.forEach(function (el) { el.classList.add('reveal'); });
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    targets.forEach(function (el) { observer.observe(el); });
  })();
});

/* Photos des formulaires de demande : on ajoute les photos une à une (prises à
   l'appareil, par exemple), on peut en retirer, et elles sont allégées avant
   l'envoi pour passer même avec une connexion mobile. Si le navigateur ne sait
   pas faire, le champ reste un envoi de fichiers ordinaire. */
document.addEventListener('DOMContentLoaded', function () {
  var MAX_SIDE = 1600, THUMB = 144;
  var canEdit = false;
  try { canEdit = typeof DataTransfer === 'function' && !!new DataTransfer().items; } catch (e) {}

  function decode(file) {
    return new Promise(function (ok, ko) {
      var r = new FileReader();
      r.onerror = ko;
      r.onload = function () {
        var i = new Image();
        i.onload = function () { ok(i); };
        i.onerror = ko;
        i.src = r.result;
      };
      r.readAsDataURL(file);
    });
  }
  function draw(img, side) {
    var k = Math.min(1, side / Math.max(img.naturalWidth, img.naturalHeight));
    var c = document.createElement('canvas');
    c.width = Math.round(img.naturalWidth * k);
    c.height = Math.round(img.naturalHeight * k);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return c;
  }
  function prepare(file) {
    return decode(file).then(function (img) {
      var thumb = draw(img, THUMB).toDataURL('image/jpeg', .7);
      return new Promise(function (ok) {
        draw(img, MAX_SIDE).toBlob(function (blob) {
          var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
          // Un format que le serveur refuse (HEIC…) est toujours converti, même si le JPEG pèse plus.
          var usual = /^image\/(jpeg|png|webp)$/.test(file.type);
          var out = blob && (blob.size < file.size || !usual) ? new File([blob], name, {type: 'image/jpeg'}) : file;
          ok({file: out, thumb: thumb});
        }, 'image/jpeg', .82);
      });
    });
  }

  document.querySelectorAll('[data-quote-photos]').forEach(function (box) {
    var input = box.querySelector('input[type=file]');
    var list  = box.querySelector('.f-photo-list');
    var msg   = box.querySelector('.f-photo-msg');
    var form  = box.closest('form');
    var max   = parseInt(box.getAttribute('data-max'), 10) || 3;
    var items = [], busy = 0;
    var submit = form ? form.querySelector('[type=submit]') : null;
    var label  = submit ? submit.textContent : '';

    function say(t) { msg.textContent = t; msg.hidden = !t; }
    function sync() {
      if (canEdit) {
        var dt = new DataTransfer();
        items.forEach(function (it) { dt.items.add(it.file); });
        input.files = dt.files;
      }
      list.textContent = '';
      items.forEach(function (it, n) {
        var d = document.createElement('div'); d.className = 'f-photo-item';
        var i = document.createElement('img'); i.src = it.thumb; i.alt = 'Photo ' + (n + 1);
        d.appendChild(i);
        if (canEdit) {
          var x = document.createElement('button');
          x.type = 'button'; x.className = 'f-photo-del'; x.textContent = '✕';
          x.setAttribute('aria-label', 'Retirer la photo ' + (n + 1));
          x.addEventListener('click', function () { items.splice(n, 1); say(''); sync(); });
          d.appendChild(x);
        }
        list.appendChild(d);
      });
    }
    function lock() { if (submit) submit.disabled = busy > 0; }

    input.addEventListener('change', function () {
      var picked = Array.prototype.slice.call(input.files || []);
      if (!canEdit) items = [];
      var room = max - items.length - busy;
      say(picked.length > room ? 'Vous pouvez envoyer ' + max + ' photos au maximum.' : '');
      picked = picked.slice(0, Math.max(0, room));
      if (canEdit) sync();   // en attendant : seules les photos déjà prêtes restent dans le champ
      picked.forEach(function (file) {
        busy++; lock();
        prepare(file).then(function (it) { items.push(it); }, function () {
          if (canEdit) say('Cette photo n\'a pas pu être lue. Formats acceptés : JPG, PNG ou WebP.');
        }).then(function () { busy--; lock(); sync(); });
      });
    });

    if (form && submit) {
      form.addEventListener('submit', function () { submit.disabled = true; submit.textContent = 'Envoi en cours…'; });
      window.addEventListener('pageshow', function () { submit.disabled = busy > 0; submit.textContent = label; });
    }
  });
});
