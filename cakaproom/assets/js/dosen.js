/* CAKAPROOM — dashboard dosen (kontrol live, penilaian kelompok, nilai individual). */
(function () {
  'use strict';
  var D = window.DOSEN, esc = CR.esc, $ = function (id) { return document.getElementById(id); };
  var KODE = ['PEMERINTAH', 'SIPIL', 'SWASTA', 'WARGA'];
  var S = { since: 0, rev: 0, sesi: null, stat: null, deadline: null, frozen: null, fails: 0, peserta: null, posts: {}, ai: {}, final: {}, firstFinal: true };
  var NS = D.api;

  function label(k) { return (D.peran[k] || {}).label || k; }
  function setTab(n) {
    document.querySelectorAll('#tabs button').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === n); });
    document.querySelectorAll('.panel').forEach(function (p) { p.classList.toggle('active', p.id === 'p-' + n); });
    if (n === 'nilai') loadPenilaian(); if (n === 'individu') loadIndividu();
  }
  document.querySelectorAll('#tabs button').forEach(function (b) { b.onclick = function () { setTab(b.dataset.tab); }; });

  /* ---------- timer ---------- */
  function paintTimer() {
    var el = $('timer'), v = null;
    if (S.frozen != null) v = S.frozen; else if (S.deadline != null) v = (S.deadline - performance.now()) / 1000;
    el.textContent = v == null ? '--:--' : CR.mmss(v);
    el.classList.toggle('pause', S.frozen != null);
    el.classList.toggle('low', v != null && S.frozen == null && v <= 60);
  }
  setInterval(paintTimer, 250);

  /* ---------- kontrol ---------- */
  function ctrl(path, body, okMsg) {
    $('ctrlErr').textContent = '';
    return CR.api('POST', NS + path, body || {}).then(function (j) {
      if (j.ok) { if (okMsg) CR.toast(okMsg); poll(true); } else { $('ctrlErr').textContent = j.error || 'Gagal.'; CR.toast(j.error || 'Gagal.', true); }
      return j;
    });
  }
  $('bBagi').onclick = function () { ctrl('/bagi-peran', {}, 'Peran dibagikan.'); };
  $('bMulai').onclick = function () { if (confirm('Mulai sesi? Fase 1 (Argumen) langsung berjalan.')) ctrl('/fase', { aksi: 'mulai' }, 'Sesi dimulai.'); };
  $('bJeda').onclick = function () { ctrl('/fase', { aksi: 'jeda' }); };
  $('bLanjut').onclick = function () { ctrl('/fase', { aksi: 'lanjut' }); };
  $('bNext').onclick = function () { ctrl('/fase', { aksi: 'berikutnya' }, 'Fase dipindahkan.'); };
  $('bAkhiri').onclick = function () { if (confirm('Akhiri sesi? Semua input dikunci dan snapshot Joint Resolution disimpan.')) ctrl('/fase', { aksi: 'akhiri' }, 'Sesi diakhiri.'); };
  document.querySelectorAll('[data-add]').forEach(function (b) { b.onclick = function () { ctrl('/fase', { aksi: 'tambah', detik: +b.dataset.add }); }; });

  function paintControls() {
    var s = S.sesi; if (!s) return;
    var lobby = s.status === 'lobby', run = s.status === 'berjalan';
    $('bBagi').disabled = !(lobby || run);
    $('bMulai').disabled = !(lobby && s.peran_dibagi);
    $('bJeda').disabled = !(run && !s.dijeda);
    $('bLanjut').disabled = !(run && s.dijeda);
    $('bNext').disabled = !(run && s.fase !== 'penutupan');
    $('bAkhiri').disabled = !(lobby || run);
    document.querySelectorAll('[data-add]').forEach(function (b) { b.disabled = !run; });
    $('fasePill').textContent = run ? s.fase_label + (s.dijeda ? ' · DIJEDA' : '') : (lobby ? 'Lobby' : 'Sesi ' + s.status);
    $('sFase').textContent = run ? s.fase_label : (lobby ? 'Lobby (menunggu mulai)' : 'Selesai');
    $('sStatus').textContent = 'status: ' + s.status + (s.peran_dibagi ? ' · peran sudah dibagi' : ' · peran belum dibagi');
  }

  function paintStat(st) {
    if (!st) return;
    $('sOnline').textContent = st.online; $('sJoin').textContent = st.bergabung; $('sRoster').textContent = st.roster;
    var box = $('peranCols');
    box.innerHTML = KODE.map(function (k) {
      var p = st.per_peran[k] || { anggota: 0, posts: {} }, po = p.posts || {};
      return '<div class="col-6 col-lg-3"><div class="role-col bl-' + k + '"><div class="d-flex justify-content-between"><strong>' + esc(label(k)) + '</strong><span class="badge text-bg-light">' + p.anggota + ' org</span></div>'
        + '<div class="small mt-1">Argumen <strong>' + (po.argumen || 0) + '</strong> · Tanya <strong>' + (po.pertanyaan || 0) + '</strong><br>Jawab <strong>' + (po.jawaban || 0) + '</strong> · Negosiasi <strong>' + (po.negosiasi || 0) + '</strong></div></div></div>';
    }).join('');
  }

  /* ---------- peserta & delegasi ---------- */
  function paintPeserta(p) {
    if (!p) return; S.peserta = p; $('pJumlah').textContent = p.jumlah;
    var rows = (p.semua || []).map(function (r) {
      return '<tr><td>' + esc(r.nama) + '</td><td>' + esc(r.nim || '') + '</td><td>' + (r.peran ? CR.peranBadge(r.peran) : '<span class="mut">—</span>') + '</td>'
        + '<td>' + (r.peran ? '<label><input type="checkbox" data-uid="' + r.user_id + '" ' + (r.delegasi ? 'checked' : '') + '> delegasi</label>' : '') + '</td></tr>';
    }).join('');
    $('pTbl').innerHTML = '<tr><th>Nama</th><th>NIM</th><th>Peran</th><th>Delegasi</th></tr>' + rows;
  }
  $('pTbl').onchange = function (e) {
    var c = e.target; if (!c.dataset.uid) return;
    CR.api('POST', NS + '/delegasi', { user_id: +c.dataset.uid, on: c.checked }).then(function (j) {
      if (!j.ok) { c.checked = !c.checked; CR.toast(j.error || 'Gagal.', true); } else poll(true);
    });
  };

  /* ---------- feed ---------- */
  function ingest(posts) {
    posts.forEach(function (p) {
      if (S.posts[p.id]) return; S.posts[p.id] = p; if (p.id > S.since) S.since = p.id;
      var d = document.createElement('div'); d.className = 'feed-item bl-' + p.peran;
      var body = p.tipe === 'argumen'
        ? '<div class="lbl">Claim</div><div>' + CR.nl(p.claim) + '</div><div class="lbl">Evidence</div><div>' + CR.nl(p.evidence) + '</div><div class="lbl">Nilai</div><div>' + CR.nl(p.nilai) + '</div>'
        : '<div>' + CR.nl(p.isi) + '</div>';
      d.innerHTML = '<div class="meta">' + CR.peranBadge(p.peran) + '<strong>' + esc(p.nama) + '</strong><span>' + esc(p.tipe) + (p.target ? ' → ' + esc(label(p.target)) : '') + '</span><span>#' + p.id + '</span><span>' + esc(p.waktu) + '</span></div>' + body;
      var f = $('dFeed'); f.insertBefore(d, f.firstChild);
    });
  }

  /* ---------- penilaian ---------- */
  function loadPenilaian() {
    CR.api('GET', NS + '/penilaian').then(function (j) { if (j.ok) { S.ai = j.skor_ai; S.final = j.final; paintPublik(j); renderScores(); } });
  }
  $('bSkor').onclick = function () {
    var b = $('bSkor'); b.disabled = true; b.textContent = 'Menghitung…'; $('nilaiErr').textContent = '';
    CR.api('POST', NS + '/skor-ai', {}).then(function (j) {
      b.disabled = false; b.textContent = 'Hitung Skor Awal AI';
      if (j.ok) { S.ai = j.skor; renderScores(); CR.toast('Skor awal AI siap.'); } else { $('nilaiErr').textContent = j.error || 'Gagal menghitung.'; }
    });
  };
  function paintPublik(j) {
    S.publik = !!j.nilai_publik;
    var b = $('bPublik'), ready = j.status === 'dinilai';
    b.disabled = !ready && !S.publik;
    b.textContent = S.publik ? 'Tarik kembali nilai (sembunyikan dari mahasiswa)' : 'Publikasikan nilai ke mahasiswa';
    b.className = 'btn ' + (S.publik ? 'btn-outline-danger' : 'btn-navy');
    $('publikInfo').innerHTML = S.publik ? '<span class="text-success fw-bold">Sudah dipublikasikan</span> ' + esc(j.nilai_publik_at || '')
      : (ready ? 'Siap dipublikasikan.' : 'Belum bisa: tetapkan nilai final keempat kelompok dahulu.');
  }
  $('bPublik').onclick = function () {
    var on = !S.publik;
    if (on && !confirm('Publikasikan nilai? Mahasiswa akan langsung dapat melihat nilai kelompok dan nilai individunya.')) return;
    CR.api('POST', NS + '/publikasi', { on: on }).then(function (j) {
      if (j.ok) { CR.toast(on ? 'Nilai dipublikasikan.' : 'Nilai ditarik kembali.'); loadPenilaian(); } else { $('nilaiErr').textContent = j.error || 'Gagal.'; }
    });
  };
  $('bSnap').onclick = function () { CR.api('POST', NS + '/snapshot', {}).then(function (j) { CR.toast(j.ok ? 'Snapshot tersimpan.' : (j.error || 'Gagal'), !j.ok); }); };

  function total(a, b, c) { return Math.round((0.35 * a + 0.35 * b + 0.30 * c) * 100) / 100; }
  function renderScores() {
    var box = $('scoreCards'), editing = document.activeElement && box.contains(document.activeElement);
    if (editing) return;   // jangan menimpa input yang sedang diedit
    box.innerHTML = KODE.map(function (k) {
      var ai = S.ai[k], fin = S.final[k];
      var v = fin || (ai ? { A: ai.A, B: ai.B, C: ai.C } : { A: '', B: '', C: '' });
      var ras = ai ? ['A', 'B', 'C'].map(function (x) {
        var r = (ai.rasional || {})[x] || {}; var nm = { A: 'Analisis Data/Hukum (35%)', B: 'Negosiasi & Empati (35%)', C: 'Nilai Kebangsaan & Etika (30%)' }[x];
        return '<div><strong>' + nm + ': ' + ai[x] + '</strong></div><div class="rasional">' + esc(r.rasional || '') + (r.bukti && r.bukti.length ? ' <em>[bukti: #' + r.bukti.join(', #') + ']</em>' : '') + '</div>';
      }).join('') : '<div class="mut small">Belum dihitung.</div>';
      return '<div class="col-lg-6"><div class="score-card bl-' + k + '" data-k="' + k + '">'
        + '<div class="d-flex justify-content-between align-items-center mb-1"><strong>' + esc(label(k)) + '</strong>'
        + (fin ? '<span class="badge text-bg-success">final ' + fin.total + ' (' + esc(fin.sumber) + ')</span>' : '<span class="badge text-bg-secondary">belum ditetapkan</span>') + '</div>'
        + (ai ? '<div class="small mut mb-1">AI: total ' + ai.total + ' · ' + esc(ai.model) + (ai.bukti_terbatas ? ' · <span class="text-warning fw-bold">bukti terbatas</span>' : '') + '</div>' : '')
        + ras
        + '<div class="row g-2 align-items-end mt-1">'
        + ['A', 'B', 'C'].map(function (x) { return '<div class="col-4"><label class="small mut">' + x + '</label><input type="number" min="0" max="100" step="0.5" class="form-control form-control-sm sc" data-x="' + x + '" value="' + v[x] + '"></div>'; }).join('')
        + '</div><div class="small mt-1">Total (35/35/30): <strong class="tot">' + (v.A !== '' ? total(+v.A, +v.B, +v.C) : '–') + '</strong></div>'
        + '<textarea class="form-control form-control-sm mt-2 cat" rows="2" placeholder="Catatan dosen">' + esc(fin ? fin.catatan : '') + '</textarea>'
        + '<button class="btn btn-sm btn-gold mt-2 tetap">' + (fin ? 'Perbarui nilai final' : 'Tetapkan nilai final') + '</button> <span class="small text-danger msg"></span>'
        + '</div></div>';
    }).join('');
  }
  $('scoreCards').oninput = function (e) {
    if (!e.target.classList.contains('sc')) return;
    var card = e.target.closest('.score-card'), g = function (x) { return parseFloat(card.querySelector('.sc[data-x="' + x + '"]').value); };
    var a = g('A'), b = g('B'), c = g('C');
    card.querySelector('.tot').textContent = (isNaN(a) || isNaN(b) || isNaN(c)) ? '–' : total(a, b, c);
  };
  $('scoreCards').onclick = function (e) {
    if (!e.target.classList.contains('tetap')) return;
    var card = e.target.closest('.score-card'), k = card.dataset.k, g = function (x) { return card.querySelector('.sc[data-x="' + x + '"]').value; };
    var msg = card.querySelector('.msg'); msg.textContent = '';
    CR.api('POST', NS + '/nilai-final', { peran: k, A: g('A'), B: g('B'), C: g('C'), catatan: card.querySelector('.cat').value }).then(function (j) {
      if (j.ok) { CR.toast('Nilai final ' + label(k) + ' ditetapkan: ' + j.total); document.activeElement.blur(); loadPenilaian(); } else msg.textContent = j.error || 'Gagal.';
    });
  };

  /* ---------- nilai individual ---------- */
  function loadIndividu() {
    CR.api('GET', NS + '/individu').then(function (j) { if (!j.ok) return; renderIndividu(j.rows); });
  }
  function renderIndividu(rows) {
    var t = $('iTbl');
    if (document.activeElement && t.contains(document.activeElement)) return;
    t.innerHTML = '<tr><th>Nama</th><th>Kelompok</th><th>Arg</th><th>Tanya</th><th>Jwb</th><th>Neg</th><th>Nilai kel.</th><th>Nilai individu</th><th>Alasan</th><th></th></tr>'
      + rows.map(function (r) {
        var has = r.nilai_kelompok != null;
        return '<tr data-uid="' + r.user_id + '"><td>' + esc(r.nama) + (+r.is_delegasi ? ' <span class="badge text-bg-warning">D</span>' : '') + '<div class="small mut">' + esc(r.nim || '') + '</div></td>'
          + '<td>' + CR.peranBadge(r.kode_peran) + '</td><td>' + r.n_argumen + '</td><td>' + r.n_pertanyaan + '</td><td>' + r.n_jawaban + '</td><td>' + r.n_negosiasi + '</td>'
          + '<td>' + (has ? r.nilai_kelompok : '–') + '</td>'
          + '<td>' + (has ? '<input type="number" min="0" max="100" step="0.5" class="form-control form-control-sm ni" style="width:90px" value="' + r.nilai_individu + '">' : '<span class="mut small">tetapkan nilai kelompok dulu</span>') + '</td>'
          + '<td>' + (has ? '<input class="form-control form-control-sm al" placeholder="wajib bila beda" value="' + esc(r.alasan || '') + '">' : '') + '</td>'
          + '<td>' + (has ? '<button class="btn btn-sm btn-navy sv">Simpan</button><div class="small text-danger er"></div>' : '') + '</td></tr>';
      }).join('');
  }
  $('iTbl').onclick = function (e) {
    if (!e.target.classList.contains('sv')) return;
    var tr = e.target.closest('tr'), er = tr.querySelector('.er'); er.textContent = '';
    CR.api('POST', NS + '/individu/' + tr.dataset.uid, { nilai: tr.querySelector('.ni').value, alasan: tr.querySelector('.al').value }).then(function (j) {
      if (j.ok) { CR.toast('Nilai individu tersimpan.'); document.activeElement.blur(); loadIndividu(); } else er.textContent = j.error || 'Gagal.';
    });
  };

  /* ---------- polling ---------- */
  var inflight = false, timer = null;
  function poll(now) {
    if (inflight) return; clearTimeout(timer); inflight = true;
    fetch(D.stateApi + '?since=' + S.since + '&rev=' + S.rev, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'gagal');
        S.fails = 0; $('conn').textContent = 'terhubung'; $('conn').classList.remove('off');
        S.sesi = j.sesi; S.rev = j.sesi.rev;
        if (j.sesi.sisa == null) { S.deadline = S.frozen = null; } else if (j.sesi.dijeda) { S.deadline = null; S.frozen = j.sesi.sisa; } else { S.frozen = null; S.deadline = performance.now() + j.sesi.sisa * 1000; }
        paintControls(); paintStat(j.stat); if (j.peserta) paintPeserta(j.peserta); if (j.posts) ingest(j.posts);
        if (S.firstFinal && (j.sesi.status === 'selesai' || j.sesi.status === 'dinilai')) { S.firstFinal = false; loadPenilaian(); }
      })
      .catch(function () { S.fails++; $('conn').textContent = 'terputus — mencoba lagi…'; $('conn').classList.add('off'); })
      .then(function () {
        inflight = false;
        timer = setTimeout(poll, Math.min(10000, D.pollMs * Math.pow(1.6, Math.min(S.fails, 5))) * (0.9 + Math.random() * 0.2));
      });
  }
  poll(true);
})();
