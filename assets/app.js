(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Formata o código enquanto se digita: K7M2-93FQ-5XB0 ------------------ */
  var ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

  function normalise(value) {
    var upper = value.toUpperCase().replace(/[^0-9A-Z]/g, '').replace(/[IL]/g, '1').replace(/O/g, '0');
    var out = '';
    for (var i = 0; i < upper.length && out.length < 12; i++) {
      if (ALPHABET.indexOf(upper.charAt(i)) !== -1) out += upper.charAt(i);
    }
    return out;
  }

  function group(code) { return code.replace(/(.{4})(?=.)/g, '$1-'); }

  Array.prototype.forEach.call(document.querySelectorAll('[data-code-input]'), function (input) {
    input.addEventListener('input', function () {
      var atEnd = input.selectionStart === input.value.length;
      var formatted = group(normalise(input.value));
      if (formatted !== input.value) {
        input.value = formatted;
        if (atEnd) input.setSelectionRange(formatted.length, formatted.length);
      }
    });
  });

  /* Entrada ao rolar ------------------------------------------------------ */
  var reveals = document.querySelectorAll('[data-reveal]');
  if (reveals.length) {
    if (reduced || !('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(reveals, function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
      Array.prototype.forEach.call(reveals, function (el) { io.observe(el); });

      // Se por qualquer motivo o observador não disparar, nada fica escondido.
      setTimeout(function () {
        Array.prototype.forEach.call(reveals, function (el) { el.classList.add('is-in'); });
      }, 2500);
    }
  }

  /* Contadores ------------------------------------------------------------ */
  var counters = document.querySelectorAll('[data-count-to]');
  if (counters.length) {
    var format = function (n) { return n.toLocaleString(document.documentElement.lang || 'pt-BR'); };
    var run = function (el) {
      var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
      if (reduced || target === 0) { el.textContent = format(target); return; }
      var started = null, duration = 1100;
      var tick = function (now) {
        if (started === null) started = now;
        var p = Math.min(1, (now - started) / duration);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = format(Math.round(target * eased));
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };
    if (!('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(counters, run);
    } else {
      var co = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { run(entry.target); co.unobserve(entry.target); }
        });
      }, { threshold: 0.6 });
      Array.prototype.forEach.call(counters, function (el) { co.observe(el); });
      // Se a animação não rodar (aba em segundo plano, rAF suspenso), o número
      // final é escrito direto. Contador preso em zero mente sobre o produto.
      setTimeout(function () {
        Array.prototype.forEach.call(counters, function (el) {
          var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
          if (el.textContent === '0' && target !== 0) el.textContent = format(target);
        });
      }, 2500);
    }
  }

  /* Cabeçalho ganha régua ao descolar do topo ----------------------------- */
  var header = document.querySelector('[data-sticky-header]');
  if (header) {
    var sync = function () { header.classList.toggle('is-stuck', window.scrollY > 8); };
    sync();
    window.addEventListener('scroll', sync, { passive: true });
  }

  /* Copiar link ----------------------------------------------------------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-copy]'), function (button) {
    button.addEventListener('click', function () {
      var url = button.getAttribute('data-copy');
      var done = button.getAttribute('data-copied') || 'OK';
      var original = button.textContent;
      var finish = function () {
        button.textContent = done;
        setTimeout(function () { button.textContent = original; }, 2200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(finish, function () {});
        return;
      }
      var field = document.createElement('textarea');
      field.value = url; field.setAttribute('readonly', '');
      field.style.position = 'absolute'; field.style.left = '-9999px';
      document.body.appendChild(field); field.select();
      try { document.execCommand('copy'); finish(); } catch (err) {}
      document.body.removeChild(field);
    });
  });

  /* QR Codes -------------------------------------------------------------- */
  var targets = document.querySelectorAll('[data-qr]');
  if (!targets.length) return;

  function draw() {
    if (typeof window.QRCode !== 'function') return;
    Array.prototype.forEach.call(targets, function (node) {
      var value = node.getAttribute('data-qr');
      if (!value || node.getAttribute('data-qr-done')) return;
      var size = node.clientWidth > 0 ? node.clientWidth - 8 : 96;
      try {
        new window.QRCode(node, {
          text: value, width: size, height: size,
          colorDark: '#0A1020', colorLight: '#FFFFFF',
          correctLevel: window.QRCode.CorrectLevel.M
        });
        node.setAttribute('data-qr-done', '1');
      } catch (err) {}
    });
  }

  var script = document.createElement('script');
  script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
  script.async = true;
  script.onload = draw;
  document.head.appendChild(script);
})();
