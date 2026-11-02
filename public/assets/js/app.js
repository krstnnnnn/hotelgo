document.documentElement.classList.add('js');
(function () {
  // ask before dangerous buttons: <form data-confirm="Are you sure?">
  document.addEventListener('submit', function (e) {
    var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (m && !window.confirm(m)) e.preventDefault();
  });
  // show / hide password
  document.querySelectorAll('[data-toggle-password]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.getAttribute('data-toggle-password'));
      if (i) { i.type = i.type === 'password' ? 'text' : 'password'; }
    });
  });
  // hourly / overnight switch on the search form
  var seg = document.querySelectorAll('input[name=stay]');
  function sync() {
    var over = document.querySelector('input[name=stay]:checked');
    over = over && over.value === 'overnight';
    document.querySelectorAll('[data-hourly-only]').forEach(function (el) { el.hidden = over; });
  }
  seg.forEach(function (r) { r.addEventListener('change', sync); }); if (seg.length) sync();
  // count-down for the 10-minute room hold
  var c = document.querySelector('[data-countdown]');
  if (c) {
    var end = Date.now() + parseInt(c.getAttribute('data-countdown'), 10) * 1000;
    (function tick() {
      var s = Math.max(0, Math.round((end - Date.now()) / 1000));
      c.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
      if (s > 0) setTimeout(tick, 1000); else location.reload();
    })();
  }
})();
// print buttons (the page has a strict security policy, so no inline "onclick")
document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });


/* ===== landing page ===== */
(function () {
  var nav = document.getElementById('nav');
  var burger = document.querySelector('.burger'), links = document.getElementById('navlinks');
  if (burger && links) {
    burger.addEventListener('click', function () { var o = links.classList.toggle('open'); burger.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    links.addEventListener('click', function (e) { if (e.target.closest('a')) { links.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); } });
  }
  var hero = document.querySelector('[data-parallax]');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function onScroll() {
    if (nav) nav.classList.toggle('scrolled', window.scrollY > 8);
    if (hero && !reduce && window.scrollY < 900) hero.style.transform = 'translateY(' + Math.round(window.scrollY * 0.25) + 'px)';
    if (!reduce) document.querySelectorAll('[data-parallax2]').forEach(function (el) { var r = el.parentNode.getBoundingClientRect(); if (r.bottom > 0 && r.top < window.innerHeight) el.style.transform = 'translateY(' + Math.round((r.top - window.innerHeight / 2) * -0.12) + 'px)'; });
  }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // fade-in on scroll
  var items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); } }); }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
  } else { items.forEach(function (el) { el.classList.add('in'); }); }

  // map: choose a place
  var frame = document.getElementById('lp-map-frame');
  document.querySelectorAll('[data-map]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (frame) frame.src = b.getAttribute('data-map');
      document.querySelectorAll('[data-map]').forEach(function (x) { x.classList.toggle('on', x === b); });
    });
  });

  // guest voices slider
  var sl = document.querySelector('[data-slider]');
  if (sl) {
    var slides = sl.querySelectorAll('.lp-voice'), dots = sl.querySelectorAll('.lp-dots button'), cur = 0, timer;
    var show = function (n) { cur = (n + slides.length) % slides.length; slides.forEach(function (s, i) { s.classList.toggle('on', i === cur); }); dots.forEach(function (d, i) { d.classList.toggle('on', i === cur); }); };
    var auto = function () { clearInterval(timer); if (!reduce) timer = setInterval(function () { show(cur + 1); }, 6000); };
    dots.forEach(function (d, i) { d.addEventListener('click', function () { show(i); auto(); }); }); auto();
  }

  // gallery lightbox
  var lb = document.getElementById('lightbox');
  if (lb) {
    var shots = Array.prototype.slice.call(document.querySelectorAll('.lp-g')), idx = 0, lbImg = lb.querySelector('img'), lbCap = lb.querySelector('p');
    var open = function (i) { idx = (i + shots.length) % shots.length; lbImg.src = shots[idx].getAttribute('data-full'); lbImg.alt = shots[idx].getAttribute('data-alt'); lbCap.textContent = shots[idx].getAttribute('data-alt'); lb.hidden = false; document.body.style.overflow = 'hidden'; };
    var close = function () { lb.hidden = true; document.body.style.overflow = ''; };
    shots.forEach(function (s, i) { s.addEventListener('click', function () { open(i); }); });
    lb.querySelector('.lb-x').addEventListener('click', close);
    lb.querySelector('.lb-prev').addEventListener('click', function () { open(idx - 1); });
    lb.querySelector('.lb-next').addEventListener('click', function () { open(idx + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    document.addEventListener('keydown', function (e) { if (lb.hidden) return; if (e.key === 'Escape') close(); if (e.key === 'ArrowLeft') open(idx - 1); if (e.key === 'ArrowRight') open(idx + 1); });
  }

  // contact form: opens the visitor's email app with the message ready
  var cf = document.querySelector('[data-mailto]');
  if (cf) cf.addEventListener('submit', function (e) {
    e.preventDefault();
    var n = cf.elements.name.value.trim(), m = cf.elements.email.value.trim(), t = cf.elements.message.value.trim(), note = cf.querySelector('[data-mailto-note]');
    if (!n || !/^\S+@\S+\.\S+$/.test(m) || !t) { note.textContent = 'Please fill in your name, a valid email and a message.'; note.style.color = '#B3261E'; return; }
    note.style.color = ''; note.textContent = 'Opening your email app…';
    location.href = 'mailto:' + cf.getAttribute('data-mailto') + '?subject=' + encodeURIComponent('HOTELGO: ' + (cf.elements.subject ? cf.elements.subject.value : 'message') + ' (' + n + ')') + '&body=' + encodeURIComponent(t + '\n\n' + n + ' (' + m + ')');
  });
})();
