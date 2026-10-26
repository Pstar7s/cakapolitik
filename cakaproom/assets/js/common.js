/* CAKAPROOM — utilitas bersama (vanilla JS). Semua teks pengguna di-escape. */
(function (w) {
  'use strict';
  var meta = document.querySelector('meta[name="csrf"]');
  var CR = {
    csrf: meta ? meta.content : '',
    esc: function (s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    },
    /** teks buatan pengguna -> HTML aman, baris baru dipertahankan */
    nl: function (s) { return CR.esc(s).replace(/\n/g, '<br>'); },
    uuid: function () {
      if (w.crypto && crypto.randomUUID) return crypto.randomUUID().replace(/-/g, '');
      return (Date.now().toString(36) + Math.random().toString(36).slice(2, 12) + Math.random().toString(36).slice(2, 12)).slice(0, 40);
    },
    words: function (s) {
      var m = String(s || '').trim().match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu);
      return m ? m.length : 0;
    },
    toast: function (msg, err) {
      var box = document.getElementById('toasts'); if (!box) return;
      var t = document.createElement('div'); t.className = 't' + (err ? ' err' : ''); t.textContent = msg;
      box.appendChild(t); setTimeout(function () { t.remove(); }, err ? 6000 : 3500);
    },
    /** fetch JSON; selalu mengembalikan objek {ok,...,_status}; tidak melempar pada HTTP error */
    api: function (method, url, body) {
      var opt = { method: method, headers: { 'Accept': 'application/json', 'X-CSRF-Token': CR.csrf }, credentials: 'same-origin' };
      if (body !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
      return fetch(url, opt).then(function (r) {
        return r.json().catch(function () { return { ok: false, error: 'Respons server tidak valid.' }; })
          .then(function (j) { j._status = r.status; return j; });
      });
    },
    mmss: function (s) {
      if (s == null) return '--:--';
      var neg = s < 0; s = Math.abs(Math.round(s));
      var m = Math.floor(s / 60), d = s % 60;
      return (neg ? '+' : '') + (m < 10 ? '0' : '') + m + ':' + (d < 10 ? '0' : '') + d;
    },
    peranBadge: function (kode) {
      var p = (w.ROOM && ROOM.peran || w.DOSEN && DOSEN.peran || {})[kode];
      return '<span class="badge-peran bg-' + CR.esc(kode) + '">' + CR.esc(p ? p.label : kode) + '</span>';
    }
  };
  w.CR = CR;
})(window);
