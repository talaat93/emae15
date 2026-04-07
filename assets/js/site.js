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

  /* Tracking Google Ads — appels téléphoniques */
  document.querySelectorAll('a[href^="tel:"]').forEach(function(l){
    l.addEventListener('click',function(){
      if(typeof gtag!=='undefined'){
        gtag('event','conversion',{'send_to':(window._gAdsId||'')+'/'+(window._gAdsCv||'')});
        gtag('event','phone_call',{'event_category':'contact'});
      }
    });
  });

  /* Tracking formulaires */
  document.querySelectorAll('form').forEach(function(f){
    f.addEventListener('submit',function(){
      if(typeof gtag!=='undefined'){
        gtag('event','generate_lead',{'event_category':'lead','event_label':'form_submit'});
        if(window._gAdsId&&window._gAdsCv){
          gtag('event','conversion',{'send_to':window._gAdsId+'/'+window._gAdsCv});
        }
      }
    });
  });
});
