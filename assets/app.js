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

/* ==========================================================================
   Impressão digital de arquivo.
   Tudo acontece no navegador: o arquivo nunca é enviado, só o SHA-256 dele.
   Isso dá três coisas que nenhum concorrente oferece — registrar sem subir
   o documento, conferir se o PDF em mãos é o registrado, e achar o registro
   a partir do próprio arquivo, sem código nenhum.
   ========================================================================== */
(function () {
  'use strict';

  var LIMITE = 25 * 1024 * 1024;

  function disponivel() {
    return window.crypto && window.crypto.subtle && typeof window.crypto.subtle.digest === 'function';
  }

  function sha256(file) {
    return file.arrayBuffer().then(function (buf) {
      return window.crypto.subtle.digest('SHA-256', buf);
    }).then(function (digest) {
      var bytes = new Uint8Array(digest), out = '';
      for (var i = 0; i < bytes.length; i++) out += bytes[i].toString(16).padStart(2, '0');
      return out;
    });
  }

  function mostrar(el, texto, tipo) {
    if (!el) return;
    el.textContent = texto;
    el.hidden = false;
    el.setAttribute('data-tone', tipo || 'neutral');
  }

  /* Formulário de registro: calcula o hash e preenche os campos ocultos. */
  var entrada = document.querySelector('[data-hash-input]');
  if (entrada) {
    var alvoHash = document.querySelector('[data-hash-value]');
    var alvoNome = document.querySelector('[data-hash-name]');
    var estado = document.querySelector('[data-hash-state]');
    entrada.addEventListener('change', function () {
      var file = entrada.files && entrada.files[0];
      if (!file) { if (alvoHash) alvoHash.value = ''; if (alvoNome) alvoNome.value = ''; if (estado) estado.hidden = true; return; }
      if (!disponivel() || file.size > LIMITE) { mostrar(estado, entrada.getAttribute('data-error') || 'Não foi possível ler o arquivo.', 'bad'); return; }
      mostrar(estado, 'Lendo o arquivo…', 'neutral');
      sha256(file).then(function (hex) {
        if (alvoHash) alvoHash.value = hex;
        if (alvoNome) alvoNome.value = file.name.slice(0, 190);
        mostrar(estado, 'Impressão digital calculada. O arquivo permanece no seu computador.', 'ok');
      }).catch(function () {
        mostrar(estado, 'Não foi possível ler o arquivo. Você pode registrar sem ele.', 'bad');
      });
    });
  }

  /* Página de verificação: o arquivo em mãos é o registrado? */
  var conferencia = document.querySelector('[data-filecheck]');
  if (conferencia) {
    var esperado = (conferencia.getAttribute('data-expect') || '').toLowerCase();
    var campo = conferencia.querySelector('[data-filecheck-input]');
    var saida = conferencia.querySelector('[data-filecheck-state]');
    if (campo && saida) {
      campo.addEventListener('change', function () {
        var file = campo.files && campo.files[0];
        if (!file) return;
        if (!disponivel() || file.size > LIMITE) { mostrar(saida, saida.getAttribute('data-error'), 'bad'); return; }
        mostrar(saida, saida.getAttribute('data-working'), 'neutral');
        sha256(file).then(function (hex) {
          var bate = hex === esperado;
          mostrar(saida, saida.getAttribute(bate ? 'data-match' : 'data-nomatch'), bate ? 'ok' : 'bad');
        }).catch(function () {
          mostrar(saida, saida.getAttribute('data-error'), 'bad');
        });
      });
    }
  }

  /* Home: achar o registro pelo próprio arquivo. */
  var busca = document.querySelector('[data-lookup]');
  if (busca) {
    var campoBusca = busca.querySelector('[data-lookup-input]');
    var saidaBusca = busca.querySelector('[data-lookup-state]');
    if (!disponivel()) { busca.hidden = true; return; }
    if (campoBusca && saidaBusca) {
      campoBusca.addEventListener('change', function () {
        var file = campoBusca.files && campoBusca.files[0];
        if (!file) return;
        if (file.size > LIMITE) { mostrar(saidaBusca, busca.getAttribute('data-error'), 'bad'); return; }
        mostrar(saidaBusca, busca.getAttribute('data-working'), 'neutral');
        sha256(file).then(function (hex) {
          var body = new URLSearchParams(); body.append('sha256', hex);
          return fetch('/api/v1/lookup', { method: 'POST', body: body, credentials: 'same-origin' });
        }).then(function (r) {
          return r.json().then(function (j) { return { ok: r.ok, j: j }; });
        }).then(function (res) {
          if (res.ok && res.j && res.j.code) {
            window.location.href = busca.getAttribute('data-url') + res.j.code;
            return;
          }
          mostrar(saidaBusca, busca.getAttribute('data-notfound'), 'bad');
        }).catch(function () {
          mostrar(saidaBusca, busca.getAttribute('data-error'), 'bad');
        });
      });
    }
  }
})();

/* Importação: o seletor de curso só interessa ao modo «emitir». */
(function () {
  'use strict';
  var modos = document.querySelectorAll('[data-mode]');
  var bloco = document.querySelector('[data-mode-issue]');
  if (!modos.length || !bloco) return;
  var sync = function () {
    var emitir = document.querySelector('[data-mode][value="issue"]');
    bloco.hidden = !(emitir && emitir.checked);
  };
  Array.prototype.forEach.call(modos, function (el) { el.addEventListener('change', sync); });
  sync();
})();
