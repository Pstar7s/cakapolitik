(function () {
  'use strict';

  var BASE = document.querySelector('meta[name="base-url"]').content.replace(/\/$/, '');
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;

  var log = document.getElementById('chatLog');
  var panel = document.getElementById('chatPanel');
  var composer = document.getElementById('composer');
  var input = document.getElementById('composerInput');
  var sendBtn = document.getElementById('composerSend');
  var hint = document.getElementById('composerHint');

  var state = { persona: null, mode: 'identity' };

  // ---- helpers ---------------------------------------------------------
  function api(path, body) {
    return fetch(BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }

  function getJSON(path) {
    return fetch(BASE + path, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function fmt(s) {
    return esc(s).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                 .replace(/_(.+?)_/g, '<em>$1</em>')
                 .replace(/\n/g, '<br>');
  }
  function scroll() { log.scrollTop = log.scrollHeight; }

  function addMsg(sender, text, opts) {
    opts = opts || {};
    var row = document.createElement('div');
    row.className = 'cp-msg-row ' + (sender === 'warga' ? 'warga' : '');
    var avatar = '';
    if (sender === 'brone' || sender === 'ai') {
      var src = sender === 'ai' && state.persona && state.persona.foto_url
        ? personaPhoto(state.persona.foto_url) : BASE + '/assets/img/brone-maskot.png';
      avatar = '<img class="cp-msg-avatar" src="' + esc(src) + '" alt="">';
    }
    var name = sender === 'brone' ? 'BRONE' : (sender === 'ai' ? (state.persona ? state.persona.nama : 'Persona AI') : 'Anda');
    var cls = sender === 'warga' ? 'cp-msg-warga' : (sender === 'ai' ? 'cp-msg-ai' : 'cp-msg-brone');
    var label = opts.label ? '<div class="cp-ai-label">🤖 ' + esc(opts.label) + '</div>' : '';
    var bubble = '<div class="cp-msg ' + cls + '"><span class="cp-sender">' + esc(name) + '</span>' + fmt(text) + '</div>';
    row.innerHTML = sender === 'warga' ? bubble : avatar + '<div>' + bubble + label + '</div>';
    log.appendChild(row);
    scroll();
  }

  function personaPhoto(u) {
    if (!u) return BASE + '/assets/img/brone-maskot.png';
    if (/^https?:/.test(u)) return u;
    return BASE + '/' + u.replace(/^\//, '');
  }

  var typingEl = null;
  function typing(on) {
    if (on && !typingEl) {
      typingEl = document.createElement('div');
      typingEl.className = 'cp-typing';
      typingEl.innerHTML = '<span></span><span></span><span></span>';
      log.appendChild(typingEl); scroll();
    } else if (!on && typingEl) {
      typingEl.remove(); typingEl = null;
    }
  }

  function setPanel(tplId) {
    panel.innerHTML = '';
    if (!tplId) return null;
    var node = document.getElementById(tplId).content.cloneNode(true);
    panel.appendChild(node);
    return panel;
  }

  function composerEnabled(on, placeholder) {
    input.disabled = !on; sendBtn.disabled = !on;
    input.placeholder = placeholder || 'Tulis pesan…';
    if (on) input.focus();
  }

  function botSay(texts, cb) {
    // sequential typing effect for one or more messages
    var arr = Array.isArray(texts) ? texts.slice() : [texts];
    (function next() {
      if (!arr.length) { if (cb) cb(); return; }
      typing(true);
      setTimeout(function () {
        typing(false);
        addMsg('brone', arr.shift());
        setTimeout(next, 250);
      }, 500);
    })();
  }

  // ---- flow: identity -------------------------------------------------
  function startIdentity() {
    state.mode = 'identity';
    composerEnabled(false);
    hint.textContent = 'Lengkapi data diri di atas untuk mulai.';
    setPanel('tpl-identity');
    var f = document.getElementById('identityForm');
    f.addEventListener('submit', onIdentity);
    wireRegionSelects(f);
  }

  function fillSelect(sel, items, placeholder) {
    sel.innerHTML = '<option value="">' + placeholder + '</option>';
    items.forEach(function (it) {
      var o = document.createElement('option');
      o.value = it.kode; o.textContent = it.nama;
      sel.appendChild(o);
    });
  }

  function wireRegionSelects(f) {
    var kab = f.querySelector('#wKabkota');
    var kec = f.querySelector('#wKecamatan');
    var kel = f.querySelector('#wKelurahan');

    getJSON('/api/wilayah?level=kabkota').then(function (j) {
      if (j.ok) fillSelect(kab, j.items, '— pilih kabupaten/kota —');
    });

    kab.addEventListener('change', function () {
      kec.disabled = true; kel.disabled = true;
      fillSelect(kec, [], '— memuat… —'); fillSelect(kel, [], '— pilih kecamatan dulu —');
      if (!kab.value) { fillSelect(kec, [], '— pilih kabupaten/kota dulu —'); return; }
      getJSON('/api/wilayah?level=kecamatan&parent=' + encodeURIComponent(kab.value)).then(function (j) {
        if (j.ok) { fillSelect(kec, j.items, '— pilih kecamatan —'); kec.disabled = false; }
      });
    });

    kec.addEventListener('change', function () {
      kel.disabled = true;
      fillSelect(kel, [], '— memuat… —');
      if (!kec.value) { fillSelect(kel, [], '— pilih kecamatan dulu —'); return; }
      getJSON('/api/wilayah?level=kelurahan&parent=' + encodeURIComponent(kec.value)).then(function (j) {
        if (j.ok) { fillSelect(kel, j.items, '— pilih kelurahan/desa —'); kel.disabled = false; }
      });
    });
  }

  function onIdentity(e) {
    e.preventDefault();
    var f = e.target, err = f.querySelector('[data-err]');
    err.textContent = '';
    if (!f.kode_kelurahan.value) { err.textContent = 'Pilih kabupaten/kota, kecamatan, dan kelurahan/desa Anda.'; return; }
    var btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    var data = Object.fromEntries(new FormData(f).entries());
    data.anonim = f.anonim.checked;
    var kabTxt = f.wKabkota.selectedOptions[0] ? f.wKabkota.selectedOptions[0].textContent : '';
    var kecTxt = f.wKecamatan.selectedOptions[0] ? f.wKecamatan.selectedOptions[0].textContent : '';
    var kelTxt = f.wKelurahan.selectedOptions[0] ? f.wKelurahan.selectedOptions[0].textContent : '';
    addMsg('warga', data.nama + ' — ' + [kelTxt, kecTxt, kabTxt, 'Jawa Timur'].filter(Boolean).join(', '));

    api('/api/otp/request', data).then(function (j) {
      btn.disabled = false;
      if (!j.ok) {
        err.textContent = j.error || 'Gagal.';
        if (j.fields) err.textContent += ' (' + Object.values(j.fields).join(' ') + ')';
        return;
      }
      botSay(j.brone, function () { startOtp(j); });
    }).catch(function () { btn.disabled = false; err.textContent = 'Koneksi bermasalah.'; });
  }

  // ---- flow: OTP ----------------------------------------------------
  function startOtp(j) {
    state.mode = 'otp';
    setPanel('tpl-otp');
    var form = document.getElementById('otpForm');
    var resend = document.getElementById('otpResend');
    form.addEventListener('submit', onOtp);
    resend.addEventListener('click', function () {
      resend.disabled = true;
      api('/api/otp/request', { resend: true }).then(function (r) {
        if (r.ok) { showDev(r); countdown(resend, r.resend_in || 60); addMsg('brone', 'Kode baru sudah dikirim.'); }
        else { form.querySelector('[data-err]').textContent = r.error || ''; resend.disabled = false; }
      });
    });
    showDev(j);
    countdown(resend, j.resend_in || 60);
    document.querySelector('#otpForm input[name=code]').focus();
  }

  function showDev(j) {
    if (!j.dev_code) return;
    var d = document.getElementById('devCode');
    if (d) { d.classList.remove('d-none'); d.textContent = 'MODE DEV — kode OTP: ' + j.dev_code; }
  }

  function countdown(btn, secs) {
    var n = secs;
    btn.disabled = true; btn.textContent = 'Kirim ulang (' + n + ')';
    var t = setInterval(function () {
      n--;
      if (n <= 0) { clearInterval(t); btn.disabled = false; btn.textContent = 'Kirim ulang'; }
      else btn.textContent = 'Kirim ulang (' + n + ')';
    }, 1000);
  }

  function onOtp(e) {
    e.preventDefault();
    var f = e.target, err = f.querySelector('[data-err]');
    err.textContent = '';
    var code = f.code.value.trim();
    addMsg('warga', 'Kode: ' + code.replace(/./g, '•'));
    api('/api/otp/verify', { code: code }).then(function (j) {
      if (!j.ok) { err.textContent = j.error || 'Gagal.'; return; }
      botSay(j.brone, function () { showPersonas(j.personas); });
    });
  }

  // ---- flow: persona menu ----------------------------------------
  function showPersonas(personas) {
    state.mode = 'menu';
    state.persona = null;
    composerEnabled(false);
    hint.textContent = 'Pilih salah satu tokoh di atas untuk mulai berdialog.';
    setPanel('tpl-personas');
    var wrap = document.getElementById('personaList');
    (personas || []).forEach(function (p) {
      var col = document.createElement('div');
      col.className = 'col-md-6';
      col.innerHTML =
        '<div class="cp-persona-pick d-flex gap-2 h-100" data-id="' + p.id + '">' +
          '<img class="cp-persona-photo" src="' + esc(personaPhoto(p.foto_url)) + '" alt="">' +
          '<div><div class="fw-bold">' + esc(p.nama) + '</div>' +
          '<div class="small text-muted">' + esc(p.partai || '') + ' · ' + esc(p.jabatan || '') + '</div></div>' +
        '</div>';
      col.querySelector('.cp-persona-pick').addEventListener('click', function () { pickPersona(p); });
      wrap.appendChild(col);
    });
    if (!personas || !personas.length) {
      wrap.innerHTML = '<div class="text-muted small">Belum ada persona aktif di dapil ini.</div>';
    }
    var end = document.createElement('div');
    end.className = 'col-12 mt-1';
    end.innerHTML = '<button class="btn btn-sm btn-outline-danger" id="menuClose">Akhiri sesi</button>';
    end.querySelector('#menuClose').addEventListener('click', closeSession);
    wrap.appendChild(end);
  }

  function pickPersona(p) {
    addMsg('warga', 'Saya ingin bicara dengan ' + p.nama + ' (' + (p.partai || '') + ')');
    typing(true);
    api('/api/chat/brone', { action: 'choose_persona', persona_id: p.id }).then(function (j) {
      typing(false);
      if (!j.ok) { addMsg('brone', j.error || 'Gagal.'); return; }
      if (!j.handoff) { addMsg('brone', j.brone); return; }
      state.persona = j.persona;
      state.mode = 'persona';
      addMsg('brone', j.brone);
      if (j.opening) addMsg('ai', j.opening, { label: j.opening_label });
      setPanel('tpl-persona-actions');
      panel.querySelectorAll('[data-act]').forEach(function (b) {
        b.addEventListener('click', function () {
          b.dataset.act === 'back' ? backToMenu() : closeSession();
        });
      });
      composerEnabled(true, 'Tulis pertanyaan atau aspirasi untuk ' + j.persona.nama + '…');
      hint.textContent = 'Setiap aspirasi otomatis dicatat & dikategorikan.';
    });
  }

  function backToMenu() {
    api('/api/chat/brone', { action: 'back_to_menu' }).then(function (j) {
      if (j.ok) { addMsg('brone', j.brone); showPersonas(j.personas); }
    });
  }

  function closeSession() {
    api('/api/chat/brone', { action: 'close' }).then(function (j) {
      if (j.poll && j.poll.length) { showPoll(j.brone, j.poll); return; }
      finishSession(j.brone);
    });
  }

  function finishSession(broneText) {
    addMsg('brone', broneText || 'Sampai jumpa!');
    state.mode = 'done';
    composerEnabled(false, 'Sesi selesai.');
    setPanel(null);
    hint.innerHTML = '<a href="' + BASE + '/dashboard">Lihat dashboard aspirasi →</a>';
  }

  // ---- flow: jajak pendapat (poll) ------------------------------
  function showPoll(intro, questions) {
    if (intro) addMsg('brone', intro);
    state.mode = 'poll';
    composerEnabled(false, 'Isi jajak pendapat pada panel di atas…');
    hint.textContent = 'Pilih satu prioritas per bidang. Bidang boleh dilewati.';
    var wrap = setPanel('tpl-poll');
    var box = wrap.querySelector('#pollBox');
    questions.forEach(function (q) {
      var fs = document.createElement('fieldset');
      fs.className = 'cp-poll-q';
      fs.dataset.qid = q.id;
      var h = '<legend class="fw-semibold small mb-1">' + esc(q.judul) + '</legend>';
      if (q.deskripsi) h += '<div class="text-muted small mb-1">' + esc(q.deskripsi) + '</div>';
      (q.opsi || []).forEach(function (o) {
        var id = 'pq' + q.id + 'o' + o.id;
        h += '<div class="form-check">' +
          '<input class="form-check-input" type="radio" name="pq' + q.id + '" id="' + id + '" value="' + o.id + '">' +
          '<label class="form-check-label small" for="' + id + '">' + esc(o.label) + '</label></div>';
      });
      fs.innerHTML = h;
      box.appendChild(fs);
    });
    wrap.querySelector('#pollSubmit').addEventListener('click', function () { submitPoll(false); });
    wrap.querySelector('#pollSkip').addEventListener('click', function (e) { e.preventDefault(); submitPoll(true); });
  }

  function submitPoll(skip) {
    var body = { skip: !!skip };
    if (!skip) {
      var answers = {};
      panel.querySelectorAll('.cp-poll-q').forEach(function (fs) {
        var sel = fs.querySelector('input[type=radio]:checked');
        if (sel) answers[fs.dataset.qid] = Number(sel.value);
      });
      if (!Object.keys(answers).length) { submitPoll(true); return; }
      body.answers = answers;
      addMsg('warga', 'Mengirim ' + Object.keys(answers).length + ' pilihan aspirasi.');
    } else {
      addMsg('warga', 'Lewati jajak pendapat.');
    }
    setPanel(null);
    typing(true);
    api('/api/poll/submit', body).then(function (j) {
      typing(false);
      finishSession(j.brone);
    }).catch(function () { typing(false); finishSession(); });
  }

  // ---- composer (persona chat) ---------------------------------
  composer.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text || state.mode !== 'persona') return;
    input.value = ''; auto();
    addMsg('warga', text);
    composerEnabled(false);
    typing(true);
    api('/api/chat/persona', { message: text }).then(function (j) {
      typing(false);
      composerEnabled(true);
      if (!j.ok) { addMsg('brone', j.error || 'Maaf, terjadi kendala.'); return; }
      addMsg('ai', j.reply, { label: j.label });
      if (j.stub) { /* demo mode banner already in reply */ }
      if (j.aspirasi) {
        var a = j.aspirasi;
        var msg = a.status === 'lolos'
          ? '✅ Aspirasi tercatat — kategori: ' + a.kategori
          : '⚠️ Aspirasi Anda ditahan moderasi otomatis: ' + (a.alasan || '');
        addMsg('brone', msg);
      }
    }).catch(function () { typing(false); composerEnabled(true); addMsg('brone', 'Koneksi bermasalah.'); });
  });

  function auto() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  }
  input.addEventListener('input', auto);
  input.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === 'Return') && !e.shiftKey) { e.preventDefault(); composer.requestSubmit(); }
  });

  // ---- boot -------------------------------------------------------
  api('/api/session/reset', {}).then(function (j) {
    botSay(j.brone || 'Halo! Saya BRONE.', startIdentity);
  }).catch(startIdentity);
})();
