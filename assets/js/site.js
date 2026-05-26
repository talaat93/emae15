document.addEventListener('DOMContentLoaded', function () {
  /* Nav mobile */
  var toggle = document.querySelector('.nav-toggle');
  var nav    = document.querySelector('.site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      nav.classList.toggle('open');
      document.body.style.overflow = nav.classList.contains('open') ? 'hidden' : '';
    });
    nav.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', function(){
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded','false');
        document.body.style.overflow='';
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

  /* Formulaires */
  document.querySelectorAll('form').forEach(function(f){
    f.addEventListener('submit',function(){
      if(typeof gtag!=='undefined') gtag('event','generate_lead',{'event_category':'lead','event_label':'form_submit'});
      gads_fire(window._gAdsCv||[]);
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
});