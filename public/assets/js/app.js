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
