/* CAKAPROOM — ruang sesi mahasiswa.
 * Adapter real-time: polling GET /state?since=&rev=&res_rev= (backend = sumber
 * kebenaran). Kontrak endpoint sama bila nanti diganti pub/sub/WebSocket:
 * push hanya menjadi pemicu untuk memanggil state() lebih cepat. */
(function () {
  'use strict';
  var R = window.ROOM, esc = CR.esc, $ = function (id) { return document.getElementById(id); };
  var FASE_TAB = { lobby: 'brief', argumen: 'argumen', uji_silang: 'uji', negosiasi: 'nego', penutupan: 'nego' };
  var KODE = ['PEMERINTAH', 'SIPIL', 'SWASTA', 'WARGA'];

  var S = {
    since: 0, rev: 0, resRev: -1, sesi: null, me: null,
    posts: {}, args: [], deadline: null, frozen: null, lastFase: null, firstLoad: true,
    klTeks: {}, klaus: [], editing: null, saveTimer: null, hbTimer: null, sikapFilled: false,
    keys: {}, fails: 0, argFilter: '', qFilter: '', joined: false
  };

  /* ---------- util ---------- */
  function label(k) { return (R.peran[k] || {}).label || k; }
  function show(el, on) { el.classList.toggle('d-none', !on); }
  function sesiAktif() { return S.sesi && S.sesi.status === 'berjalan' && !S.sesi.dijeda; }
  function fase() { return S.sesi ? S.sesi.fase : 'lobby'; }
  function lockMsg(tipeFase) {
    if (!S.sesi) return 'Memuat…';
    if (S.sesi.status === 'lobby') return 'Sesi belum dimulai. Menunggu dosen.';
    if (S.sesi.status !== 'berjalan') return 'Sesi telah berakhir. Semua input dikunci.';
    if (S.sesi.dijeda) return 'Sesi sedang dijeda dosen.';
    if (fase() !== tipeFase) return 'Bagian ini tidak aktif pada fase sekarang (' + (S.sesi.fase_label || fase()) + ').';
    return '';
  }
  function setTab(name) {
    document.querySelectorAll('#tabs button').forEach(function (b) { b.classList.toggle('active', b.dataset.tab === name); });
    document.querySelectorAll('.panel').forEach(function (p) { p.classList.toggle('active', p.id === 'p-' + name); });
  }
  document.querySelectorAll('#tabs button').forEach(function (b) {
    b.onclick = function () { setTab(b.dataset.tab); var d = b.querySelector('.dot'); if (d) d.remove(); };
  });
  function dot(tab) {
    var b = document.querySelector('#tabs button[data-tab="' + tab + '"]');
    if (b && !b.classList.contains('active') && !b.querySelector('.dot')) b.insertAdjacentHTML('beforeend', '<span class="dot"></span>');
  }
  function key(name) { if (!S.keys[name]) S.keys[name] = CR.uuid(); return S.keys[name]; }
  function resetKey(name) { delete S.keys[name]; }

  /* ---------- timer ---------- */
  function setTimer(sisa, dijeda) {
    if (sisa == null) { S.deadline = null; S.frozen = null; return; }
    if (dijeda) { S.deadline = null; S.frozen = sisa; } else { S.frozen = null; S.deadline = performance.now() + sisa * 1000; }
  }
  function paintTimer() {
    var el = $('timer'), v = null;
    if (S.frozen != null) v = S.frozen; else if (S.deadline != null) v = (S.deadline - performance.now()) / 1000;
    el.textContent = v == null ? '--:--' : CR.mmss(v);
    el.classList.toggle('pause', S.frozen != null);
    el.classList.toggle('low', v != null && S.frozen == null && v <= 60);
  }
  setInterval(paintTimer, 250);

  /* ---------- render: header & peran ---------- */
  function paintHeader() {
    var s = S.sesi; if (!s) return;
    var txt = s.status === 'lobby' ? 'Lobby' : (s.status === 'berjalan' ? s.fase_label : 'Sesi selesai');
    if (s.dijeda) txt += ' · DIJEDA';
    $('fasePill').textContent = txt;
  }
  function paintPeran() {
    var me = S.me, has = !!(me && me.peran);
    show($('lobbyBox'), !has); show($('peranBox'), has);
    if (!has) return;
    $('peranBanner').style.background = 'var(--r-' + me.peran + ')';
    $('peranNama').textContent = (R.peran[me.peran].ikon || '') + ' ' + label(me.peran);
    show($('delegBadge'), me.delegasi);
    document.querySelectorAll('.brief-peran').forEach(function (b) { show(b, b.dataset.peran === me.peran); });
    // opsi target uji silang = peran lain
    var sel = $('qTarget');
    if (!sel.dataset.built) {
      sel.dataset.built = '1';
      sel.innerHTML = KODE.filter(function (k) { return k !== me.peran; }).map(function (k) { return '<option value="' + k + '">' + esc(label(k)) + '</option>'; }).join('');
      sel.onchange = fillParent;
    }
  }
  function paintPeserta(p) {
    if (!p) return;
    var lc = $('lobbyCount'); if (lc) lc.textContent = p.jumlah;
    if (p.tim) $('timList').innerHTML = p.tim.map(function (t) { return esc(t.nama) + (t.delegasi ? ' <span class="badge text-bg-warning">delegasi</span>' : ''); }).join(' · ') || '—';
    if (p.delegasi) $('delegList').innerHTML = p.delegasi.map(function (d) { return esc(d.nama) + ' (' + esc(label(d.peran)) + ')'; }).join(', ') || '—';
  }

  /* ---------- feed argumen ---------- */
  function chips(containerId, current, onPick) {
    var c = $(containerId); if (c.dataset.built) return; c.dataset.built = '1';
    var items = [['', 'Semua']].concat(KODE.map(function (k) { return [k, label(k)]; }));
    c.innerHTML = items.map(function (i) { return '<button data-k="' + i[0] + '" class="' + (i[0] === current ? 'active' : '') + '">' + esc(i[1]) + '</button>'; }).join('');
    c.onclick = function (e) {
      var b = e.target.closest('button'); if (!b) return;
      c.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x === b); });
      onPick(b.dataset.k);
    };
  }
  function applyFilter(feedId, k) {
    document.querySelectorAll('#' + feedId + ' > .feed-item').forEach(function (it) { it.style.display = (!k || it.dataset.peran === k) ? '' : 'none'; });
  }
  function metaHtml(p, extra) {
    return '<div class="meta">' + CR.peranBadge(p.peran) + '<strong>' + esc(p.nama) + '</strong>' + (extra || '') + '<span>' + esc(p.waktu) + '</span></div>';
  }
  function addArgumen(p, fresh) {
    var d = document.createElement('div');
    d.className = 'feed-item bl-' + p.peran + (fresh ? ' baru' : ''); d.dataset.peran = p.peran; d.id = 'post-' + p.id;
    d.innerHTML = metaHtml(p, '<span>' + p.kata + ' kata</span><span>#' + p.id + '</span>')
      + '<div class="lbl">Claim</div><div>' + CR.nl(p.claim) + '</div>'
      + '<div class="lbl">Evidence</div><div>' + CR.nl(p.evidence) + '</div>'
      + '<div class="lbl">National Value Alignment</div><div>' + CR.nl(p.nilai) + '</div>';
    var feed = $('argFeed'); feed.insertBefore(d, feed.firstChild);
    S.args.push(p); $('argEmpty').style.display = 'none';
    if (fresh && p.user_id !== R.userId) dot('argumen');
  }

  /* ---------- uji silang ---------- */
  function canAnswer(q) { return S.me && q.target === S.me.peran && sesiAktif() && fase() === 'uji_silang'; }
  function addPertanyaan(p, fresh) {
    var ref = '';
    if (p.parent_id) {
      var a = S.posts[p.parent_id];
      ref = '<div class="small mut mb-1">Merujuk argumen #' + p.parent_id + (a ? ' (' + esc(a.nama) + ')' : '') + '</div>';
    }
    var d = document.createElement('div');
    d.className = 'feed-item bl-' + p.peran + (fresh ? ' baru' : ''); d.dataset.peran = p.peran; d.dataset.target = p.target; d.id = 'post-' + p.id;
    d.innerHTML = metaHtml(p, '<span>&rarr; ' + CR.peranBadge(p.target) + '</span><span>#' + p.id + '</span>') + ref
      + '<div>' + CR.nl(p.isi) + '</div><div class="thread mt-2" id="thr-' + p.id + '"></div>'
      + '<div class="ans-form mt-2 d-none" data-q="' + p.id + '"><textarea rows="2" class="form-control mb-1" placeholder="Jawaban (maks ' + R.jawabanMax + ' kata)"></textarea>'
      + '<div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-gold">Kirim jawaban</button><span class="wc small"></span><span class="small text-danger"></span></div></div>';
    var feed = $('qFeed'); feed.insertBefore(d, feed.firstChild);
    $('qEmpty').style.display = 'none';
    bindAnswer(d.querySelector('.ans-form'));
    if (fresh && S.me && p.target === S.me.peran) { dot('uji'); CR.toast('Ada pertanyaan untuk peran Anda.'); }
  }
  function bindAnswer(f) {
    var ta = f.querySelector('textarea'), btn = f.querySelector('button'), wc = f.querySelector('.wc'), er = f.querySelector('.text-danger');
    var qid = f.dataset.q, kname = 'ans' + qid;
    ta.oninput = function () { var n = CR.words(ta.value); wc.textContent = n + ' kata'; wc.className = 'wc small ' + (n >= 3 && n <= R.jawabanMax ? 'ok' : 'bad'); };
    btn.onclick = function () {
      er.textContent = ''; btn.disabled = true;
      CR.api('POST', R.api + '/post', { tipe: 'jawaban', parent_id: +qid, isi: ta.value, client_key: key(kname) }).then(function (j) {
        btn.disabled = false;
        if (j.ok) { ta.value = ''; wc.textContent = ''; resetKey(kname); poll(true); }
        else { er.textContent = j.error || 'Gagal mengirim.'; if (j._status && j._status < 500) resetKey(kname); }
      }).catch(function () { btn.disabled = false; er.textContent = 'Koneksi bermasalah; coba kirim lagi (tidak akan dobel).'; });
    };
  }
  function addJawaban(p, fresh) {
    var thr = $('thr-' + p.parent_id); if (!thr) return;
    var d = document.createElement('div');
    d.className = 'feed-item bl-' + p.peran + (fresh ? ' baru' : ''); d.id = 'post-' + p.id;
    d.innerHTML = metaHtml(p, '<span>menjawab</span><span>#' + p.id + '</span>') + '<div>' + CR.nl(p.isi) + '</div>';
    thr.appendChild(d);
  }
  function fillParent() {
    var t = $('qTarget').value, sel = $('qParent'), cur = sel.value;
    var opts = ['<option value="">— tidak merujuk argumen tertentu —</option>'];
    S.args.filter(function (a) { return a.peran === t; }).forEach(function (a) {
      opts.push('<option value="' + a.id + '">#' + a.id + ' ' + esc(a.nama) + ' — ' + esc((a.claim || '').slice(0, 60)) + '</option>');
    });
    sel.innerHTML = opts.join(''); sel.value = cur;
  }

  /* ---------- negosiasi ---------- */
  function addNego(p) {
    var log = $('negLog'), near = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
    var d = document.createElement('div'); d.className = 'neg-msg'; d.id = 'post-' + p.id;
    d.innerHTML = CR.peranBadge(p.peran) + ' <strong>' + esc(p.nama) + '</strong> <span class="small mut">' + esc(p.waktu) + '</span><div>' + CR.nl(p.isi) + '</div>';
    log.appendChild(d); if (near || p.user_id === R.userId) log.scrollTop = log.scrollHeight;
    if (S.sesi && fase() === 'negosiasi' && p.user_id !== R.userId) dot('nego');
  }

  function ingest(posts) {
    posts.forEach(function (p) {
      if (S.posts[p.id]) return;
      S.posts[p.id] = p; if (p.id > S.since) S.since = p.id;
      var fresh = !S.firstLoad;
      if (p.tipe === 'argumen') addArgumen(p, fresh);
      else if (p.tipe === 'pertanyaan') addPertanyaan(p, fresh);
      else if (p.tipe === 'jawaban') addJawaban(p, fresh);
      else if (p.tipe === 'negosiasi') addNego(p);
    });
    applyFilter('argFeed', S.argFilter); applyFilter('qFeed', S.qFilter);
    fillParent();
  }

  /* ---------- gating form ---------- */
  function gate() {
    var s = S.sesi, me = S.me; if (!s || !me) return;
    var mine = S.args.filter(function (a) { return a.user_id === R.userId; }).length;
    $('argMine').textContent = mine;
    $('argRange').textContent = s.word_min + '–' + s.word_max + ' kata';
    var m = lockMsg('argumen'); if (!m && mine >= R.maxArgumen) m = 'Batas ' + R.maxArgumen + ' argumen per mahasiswa sudah tercapai.';
    show($('argForm'), !m && !!me.peran); show($('argLocked'), !!m && fase() !== 'lobby'); $('argLocked').textContent = m;
    var q = lockMsg('uji_silang');
    show($('qForm'), !q && !!me.peran); show($('qLocked'), !!q && fase() !== 'lobby'); $('qLocked').textContent = q;
    var n = lockMsg('negosiasi');
    show($('negForm'), !n && !!me.peran); show($('negLocked'), !!n && fase() !== 'lobby'); $('negLocked').textContent = n;
    document.querySelectorAll('.ans-form').forEach(function (f) {
      var qp = S.posts[f.dataset.q]; f.classList.toggle('d-none', !(qp && canAnswer(qp)));
    });
    $('resHint').textContent = me.delegasi ? (me.bisa_sunting ? '— Anda delegasi: klik "Sunting" pada klausul' : '— Anda delegasi (penyuntingan aktif pada Fase 3)') : '— tampilan live, hanya-baca (delegasi yang menyunting)';
    show($('sikapBox'), !!me.bisa_sunting);
    if (S.editing && !me.bisa_sunting) closeEditor(true);
    paintKlausulButtons();
  }

  /* ---------- kirim argumen / pertanyaan / negosiasi ---------- */
  function wcArg() {
    var n = CR.words($('argClaim').value) + CR.words($('argEvidence').value) + CR.words($('argValue').value);
    var ok = S.sesi && n >= S.sesi.word_min && n <= S.sesi.word_max;
    $('argWc').textContent = n + ' kata'; $('argWc').className = 'wc ' + (ok ? 'ok' : 'bad');
  }
  ['argClaim', 'argEvidence', 'argValue'].forEach(function (id) { $(id).oninput = wcArg; });
  $('argSend').onclick = function () {
    var b = $('argSend'); $('argErr').textContent = ''; b.disabled = true;
    CR.api('POST', R.api + '/post', { tipe: 'argumen', claim: $('argClaim').value, evidence: $('argEvidence').value, nilai_kebangsaan: $('argValue').value, client_key: key('arg') }).then(function (j) {
      b.disabled = false;
      if (j.ok) { ['argClaim', 'argEvidence', 'argValue'].forEach(function (i) { $(i).value = ''; }); wcArg(); resetKey('arg'); CR.toast('Argumen terkirim.'); poll(true); }
      else { $('argErr').textContent = j.error || 'Gagal mengirim.'; if (j._status && j._status < 500) resetKey('arg'); }
    }).catch(function () { b.disabled = false; $('argErr').textContent = 'Koneksi bermasalah; tekan kirim lagi (tidak akan dobel).'; });
  };
  $('qText').oninput = function () { var n = CR.words($('qText').value); $('qWc').textContent = n + ' kata'; $('qWc').className = 'wc ' + (n >= 3 && n <= R.pertanyaanMax ? 'ok' : 'bad'); };
  $('qSend').onclick = function () {
    var b = $('qSend'); $('qErr').textContent = ''; b.disabled = true;
    CR.api('POST', R.api + '/post', { tipe: 'pertanyaan', target_peran: $('qTarget').value, parent_id: +$('qParent').value || 0, isi: $('qText').value, client_key: key('q') }).then(function (j) {
      b.disabled = false;
      if (j.ok) { $('qText').value = ''; $('qWc').textContent = ''; resetKey('q'); CR.toast('Pertanyaan terkirim.'); poll(true); }
      else { $('qErr').textContent = j.error || 'Gagal mengirim.'; if (j._status && j._status < 500) resetKey('q'); }
    }).catch(function () { b.disabled = false; $('qErr').textContent = 'Koneksi bermasalah; coba lagi.'; });
  };
  $('negText').oninput = function () { $('negCount').textContent = $('negText').value.length; };
  function sendNego() {
    var t = $('negText').value.trim(); if (!t) return; var b = $('negSend'); b.disabled = true;
    CR.api('POST', R.api + '/post', { tipe: 'negosiasi', isi: t, client_key: key('neg') }).then(function (j) {
      b.disabled = false;
      if (j.ok) { $('negText').value = ''; $('negCount').textContent = 0; resetKey('neg'); poll(true); }
      else { CR.toast(j.error || 'Gagal mengirim.', true); if (j._status && j._status < 500) resetKey('neg'); }
    }).catch(function () { b.disabled = false; CR.toast('Koneksi bermasalah; coba lagi.', true); });
  }
  $('negSend').onclick = sendNego;
  $('negText').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendNego(); } });

  /* ---------- Joint Resolution (kunci per klausul) ---------- */
  function buildKlausul(list) {
    var box = $('klausulBox'); if (box.dataset.built) return; box.dataset.built = '1';
    box.innerHTML = list.map(function (k) {
      return '<div class="klausul" id="kl-' + k.no + '"><div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">'
        + '<strong>' + k.no + '. ' + esc(k.judul) + '</strong><span class="lockbadge d-none" id="lk-' + k.no + '"></span></div>'
        + '<div class="small mut">' + esc(k.deskripsi) + '</div>'
        + '<div class="isi mt-2" id="isi-' + k.no + '"></div>'
        + '<div class="edit d-none mt-2" id="ed-' + k.no + '"><textarea class="form-control" maxlength="8000"></textarea>'
        + '<div class="d-flex gap-2 align-items-center mt-2"><button class="btn btn-sm btn-gold" data-act="done">Simpan &amp; selesai</button>'
        + '<span class="small mut" id="st-' + k.no + '"></span></div></div>'
        + '<button class="btn btn-sm btn-outline-secondary mt-2 d-none" data-act="edit" data-no="' + k.no + '">Sunting klausul</button></div>';
    }).join('');
    box.onclick = function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var wrap = b.closest('.klausul'), no = +wrap.id.slice(3);
      if (b.dataset.act === 'edit') openEditor(no);
      if (b.dataset.act === 'done') closeEditor(false);
    };
  }
  function paintKlausul(list, withText) {
    buildKlausul(list); S.klaus = list;
    var terisi = 0;
    list.forEach(function (k) {
      if (withText && k.teks != null) S.klTeks[k.no] = k.teks;
      var tx = S.klTeks[k.no] || ''; if (tx.trim()) terisi++;
      if (!(S.editing === k.no)) { var el = $('isi-' + k.no); el.textContent = tx || '(belum diisi)'; el.classList.toggle('mut', !tx); }
      var lk = $('lk-' + k.no), w = $('kl-' + k.no);
      if (k.kunci && !(S.editing === k.no)) { lk.textContent = '✎ disunting ' + k.kunci.nama + ' (' + label(k.kunci.peran) + ')'; show(lk, true); w.classList.add('lock'); }
      else if (S.editing === k.no) { lk.textContent = '✎ Anda sedang menyunting'; show(lk, true); w.classList.add('lock'); }
      else { show(lk, false); w.classList.remove('lock'); }
    });
    paintKlausulButtons(); S.terisi = terisi; paintSyarat();
  }
  function paintKlausulButtons() {
    document.querySelectorAll('#klausulBox button[data-act="edit"]').forEach(function (b) {
      var no = +b.dataset.no, k = S.klaus.filter(function (x) { return x.no === no; })[0];
      var lockedOther = k && k.kunci && k.kunci.user_id !== R.userId;
      show(b, !!(S.me && S.me.bisa_sunting) && S.editing !== no && !lockedOther);
    });
  }
  function paintSyarat() {
    var sk = S.sikap || {}, n = Object.keys(sk).length, total = S.klaus.length;
    $('resSyarat').innerHTML = 'Klausul terisi <strong>' + (S.terisi || 0) + '/' + total + '</strong> · sikap peran tercatat <strong>' + n + '/4</strong>'
      + ((S.terisi === total && n === 4 && total) ? ' · <span class="text-success fw-bold">memenuhi syarat sah</span>' : '');
    $('sikapAll').innerHTML = KODE.map(function (k) {
      var s = sk[k]; return '<div>' + CR.peranBadge(k) + ' ' + (s ? '<strong>' + esc(s.sikap) + '</strong>' + (s.catatan ? ' — ' + esc(s.catatan) : '') : '<span class="mut">belum</span>') + '</div>';
    }).join('');
    if (S.me && S.me.peran && sk[S.me.peran] && !S.sikapFilled) {
      S.sikapFilled = true; $('sikapCatatan').value = sk[S.me.peran].catatan || '';
      var r = document.querySelector('input[name="sikap"][value="' + sk[S.me.peran].sikap + '"]'); if (r) r.checked = true;
    }
  }
  function openEditor(no) {
    if (S.editing && S.editing !== no) closeEditor(false);
    CR.api('POST', R.api + '/resolusi/kunci', { no: no }).then(function (j) {
      if (!j.ok) { CR.toast(j.error || 'Tidak dapat menyunting.', true); poll(true); return; }
      S.editing = no;
      var ed = $('ed-' + no), ta = ed.querySelector('textarea');
      ta.value = S.klTeks[no] || ''; show(ed, true); show($('isi-' + no), false); ta.focus();
      ta.oninput = function () { $('st-' + no).textContent = 'mengetik…'; clearTimeout(S.saveTimer); S.saveTimer = setTimeout(function () { save(no, false); }, 1500); };
      S.hbTimer = setInterval(function () { save(no, false, true); }, 15000);   // perpanjang kunci
      paintKlausul(S.klaus, false);
    });
  }
  function save(no, lepas, diam) {
    var ta = $('ed-' + no).querySelector('textarea');
    return CR.api('POST', R.api + '/resolusi/simpan', { no: no, teks: ta.value, lepas: !!lepas }).then(function (j) {
      if (j.ok) { S.klTeks[no] = ta.value; if (!diam) $('st-' + no).textContent = 'tersimpan ' + new Date().toLocaleTimeString('id-ID'); }
      else { CR.toast(j.error || 'Gagal menyimpan.', true); if (j.terkunci || j._status === 403) closeEditor(true); }
      return j;
    });
  }
  function closeEditor(abaikan) {
    var no = S.editing; if (!no) return;
    clearTimeout(S.saveTimer); clearInterval(S.hbTimer); S.editing = null;
    var finish = function () { show($('ed-' + no), false); show($('isi-' + no), true); paintKlausul(S.klaus, false); poll(true); };
    if (abaikan) { finish(); } else { save(no, true).then(finish, finish); }
  }
  $('sikapSend').onclick = function () {
    var r = document.querySelector('input[name="sikap"]:checked');
    if (!r) { CR.toast('Pilih setuju atau keberatan.', true); return; }
    CR.api('POST', R.api + '/resolusi/sikap', { sikap: r.value, catatan: $('sikapCatatan').value }).then(function (j) {
      if (j.ok) { CR.toast('Sikap peran tersimpan.'); poll(true); } else CR.toast(j.error || 'Gagal.', true);
    });
  };

  /* ---------- nilai (hanya bila dosen sudah mempublikasikan) ---------- */
  var INDIKATOR = [['A', 'Kedalaman Analisis Data dan Hukum', '35%'], ['B', 'Negosiasi dan Empati Peran', '35%'], ['C', 'Nilai Kebangsaan dan Etika Politik', '30%']];
  function fmt(v) { return (Math.round(v * 100) / 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }
  function paintNilai(n, publik) {
    var tab = $('tabNilai'), box = $('nilaiBox');
    var tampil = !!(publik && n);
    show(tab, tampil);
    if (!tampil) { S.nilaiShown = false; box.innerHTML = ''; return; }
    var k = n.kelompok, i = n.individu;
    box.innerHTML = '<div class="card-cr p-3 mb-3"><div class="small mut">Nilai Anda</div>'
      + (i ? '<div class="display-5 fw-bold" style="color:var(--navy)">' + esc(fmt(i.nilai)) + '</div>'
        + (Math.abs(i.selisih) > 0.001
          ? '<div class="small mut">Disesuaikan dosen dari nilai kelompok (' + esc(fmt(i.nilai_kelompok)) + '): ' + (i.selisih > 0 ? '+' : '') + esc(fmt(i.selisih)) + ' poin.</div>'
          : '<div class="small mut">Sama dengan nilai kelompok.</div>')
        : '<div class="mut">Nilai individu belum tersedia.</div>')
      + '</div>'
      + '<div class="card-cr p-3 mb-3"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Nilai kelompok ' + CR.peranBadge(n.peran) + '</h2>'
      + '<strong class="h5 mb-0">' + esc(fmt(k.total)) + '</strong></div>'
      + '<table class="tbl"><tr><th>Indikator</th><th>Bobot</th><th>Skor</th></tr>'
      + INDIKATOR.map(function (x) { return '<tr><td>' + esc(x[1]) + '</td><td>' + x[2] + '</td><td>' + esc(fmt(k[x[0]])) + '</td></tr>'; }).join('')
      + '</table>'
      + (k.catatan ? '<div class="mt-3"><div class="small mut fw-bold">Catatan dosen untuk kelompok</div><div>' + CR.nl(k.catatan) + '</div></div>' : '')
      + '</div><p class="small mut">Nilai ditetapkan oleh dosen. Skor awal AI hanya saran untuk dosen dan tidak ditampilkan.</p>';
    if (!S.nilaiShown) {
      S.nilaiShown = true;
      if (S.firstLoad) setTab('nilai'); else { dot('nilai'); CR.toast('Dosen telah mempublikasikan nilai. Lihat tab "Nilai Saya".'); }
    }
  }

  /* ---------- state handling & polling ---------- */
  function apply(j) {
    var prevFase = S.lastFase, prevStatus = S.sesi && S.sesi.status;
    S.sesi = j.sesi; if (j.me) S.me = j.me;
    S.rev = j.sesi.rev; if (j.resolusi) S.resRev = j.sesi.res_rev;
    setTimer(j.sesi.sisa, j.sesi.dijeda);
    paintHeader(); paintPeran(); paintPeserta(j.peserta);
    chips('argFilter', S.argFilter, function (k) { S.argFilter = k; applyFilter('argFeed', k); });
    chips('qFilter', S.qFilter, function (k) { S.qFilter = k; applyFilter('qFeed', k); });
    if (j.posts) ingest(j.posts);
    if (j.resolusi) {
      if (j.resolusi.sikap) S.sikap = j.resolusi.sikap;
      paintKlausul(j.resolusi.klausul, j.resolusi.teks_dikirim);
    }
    gate();
    var f = j.sesi.status === 'berjalan' ? j.sesi.fase : (j.sesi.status === 'lobby' ? 'lobby' : 'penutupan');
    if (S.lastFase !== f) {
      if (!S.firstLoad) {
        setTab(FASE_TAB[f] || 'brief');
        CR.toast(j.sesi.status === 'berjalan' ? 'Beralih ke ' + j.sesi.fase_label : (j.sesi.status === 'lobby' ? 'Lobby' : 'Sesi berakhir. Input dikunci.'));
      } else if (j.sesi.status !== 'lobby') { setTab(FASE_TAB[f] || 'brief'); }
      S.lastFase = f;
    }
    if (prevStatus === 'lobby' && j.me && j.me.peran && !S.firstLoad) CR.toast('Peran Anda: ' + label(j.me.peran));
    paintNilai(j.nilai, j.sesi.nilai_publik);   // setelah penentuan tab fase agar tab Nilai menang saat dibuka
    S.firstLoad = false;
  }

  var inflight = false, timer = null;
  function poll(now) {
    if (inflight) { if (now) S.again = true; return; }
    clearTimeout(timer); inflight = true;
    var url = R.api + '/state?since=' + S.since + '&rev=' + S.rev + '&res_rev=' + S.resRev;
    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); })
      .then(function (j) {
        if (j.perlu_join && !S.joined) { S.joined = true; return CR.api('POST', R.api + '/join', {}).then(function (jj) { if (!jj.ok) throw new Error(jj.error || 'Gagal masuk sesi'); S.firstLoad = true; inflight = false; poll(true); return 'skip'; }); }
        if (!j.ok) throw new Error(j.error || 'Gagal memuat');
        S.fails = 0; setConn(true); apply(j);
      })
      .catch(function (e) { console.error(e && e.stack || e); S.fails++; setConn(false, e && e.message); })
      .then(function (skip) {
        if (skip === 'skip') return;
        inflight = false;
        var base = document.hidden ? R.pollMs * 2.5 : R.pollMs;
        var wait = Math.min(10000, base * Math.pow(1.6, Math.min(S.fails, 5))) * (0.85 + Math.random() * 0.3);
        if (S.again) { S.again = false; wait = 150; }
        timer = setTimeout(poll, wait);
      });
  }
  function setConn(ok, msg) {
    var c = $('conn'); c.textContent = ok ? 'terhubung' : 'terputus — mencoba lagi…'; c.classList.toggle('off', !ok);
    if (!ok && msg && S.fails === 1) CR.toast(msg, true);
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(true); });
  window.addEventListener('online', function () { poll(true); });

  // masuk lobby (idempoten) lalu polling
  CR.api('POST', R.api + '/join', {}).then(function (j) {
    S.joined = true;
    // join ditolak (mis. sesi sudah berakhir) bukan galat fatal: state memutuskan apakah Anda peserta.
    if (!j.ok && j._status !== 409) { $('lobbyTitle').textContent = 'Tidak dapat masuk'; }
    poll(true);
  }).catch(function () { poll(true); });
})();
