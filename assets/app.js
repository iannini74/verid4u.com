(function () {
  'use strict';

  /* Formata o código enquanto se digita: K7M2-93FQ-5XB1 ------------------ */
  var ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

  function normalise(value) {
    var upper = value.toUpperCase().replace(/[^0-9A-Z]/g, '');
    upper = upper.replace(/[IL]/g, '1').replace(/O/g, '0');
    var out = '';
    for (var i = 0; i < upper.length && out.length < 12; i++) {
      if (ALPHABET.indexOf(upper.charAt(i)) !== -1) out += upper.charAt(i);
    }
    return out;
  }

  function group(code) {
    return code.replace(/(.{4})(?=.)/g, '$1-');
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-code-input]'), function (input) {
    input.addEventListener('input', function () {
      var caretAtEnd = input.selectionStart === input.value.length;
      var formatted = group(normalise(input.value));
      if (formatted !== input.value) {
        input.value = formatted;
        if (caretAtEnd) input.setSelectionRange(formatted.length, formatted.length);
      }
    });
  });

  /* Copiar link ---------------------------------------------------------- */
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
      field.value = url;
      field.setAttribute('readonly', '');
      field.style.position = 'absolute';
      field.style.left = '-9999px';
      document.body.appendChild(field);
      field.select();
      try { document.execCommand('copy'); finish(); } catch (err) {}
      document.body.removeChild(field);
    });
  });

  /* QR Codes ------------------------------------------------------------- */
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
          text: value,
          width: size,
          height: size,
          colorDark: '#0F1721',
          colorLight: '#FFFFFF',
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
