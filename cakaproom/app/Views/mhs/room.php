<?php /** @var array $sesi,$kasus,$briefs,$klausul,$cfg,$u */ ?>
<div class="room-top">
  <div class="inner">
    <a href="<?= url('/mhs') ?>" class="text-white-50 text-decoration-none small">&larr;</a>
    <div class="room-title"><?= e($sesi['judul'] ?: $kasus['judul']) ?></div>
    <span class="fase-pill" id="fasePill">Lobby</span>
    <div class="timer" id="timer">--:--</div>
    <span class="conn" id="conn">menghubungkan…</span>
  </div>
</div>
<div class="room-tabs" id="tabs">
  <button data-tab="brief" class="active">Brief &amp; Kasus</button>
  <button data-tab="argumen">Argumen</button>
  <button data-tab="uji">Uji Silang</button>
  <button data-tab="nego">Negosiasi &amp; Resolusi</button>
  <button data-tab="nilai" id="tabNilai" class="d-none">Nilai Saya</button>
</div>

<div class="room-main">

  <!-- ===== Brief & kasus ===== -->
  <section class="panel active" id="p-brief">
    <div id="lobbyBox" class="card-cr p-4 mb-3 text-center">
      <div class="h5 mb-1" id="lobbyTitle">Anda berada di lobby</div>
      <div class="mut" id="lobbySub">Menunggu dosen membagi peran. <span id="lobbyCount">0</span> peserta sudah masuk.</div>
    </div>
    <div id="peranBox" class="d-none">
      <div class="peran-banner mb-3" id="peranBanner">
        <div class="small text-white-50">Peran Anda (tetap selama sesi)</div>
        <div class="h4 mb-1" id="peranNama">—</div>
        <div id="delegBadge" class="d-none"><span class="badge text-bg-warning">Anda adalah DELEGASI — dapat menyunting Joint Resolution pada Fase 3</span></div>
      </div>
      <?php foreach (PERAN as $kode => $p): ?>
      <div class="card-cr p-3 mb-3 brief-peran d-none" data-peran="<?= e($kode) ?>">
        <h2 class="h6 mb-2">Brief peran <?= e($p['label']) ?></h2>
        <div><?= teks($briefs[$kode] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
      <div class="card-cr p-3 mb-3"><h2 class="h6">Tim Anda</h2><div id="timList" class="small">—</div></div>
    </div>

    <div class="card-cr p-3 mb-3">
      <h2 class="h5"><?= e($kasus['judul']) ?></h2>
      <?php if (!empty($kasus['pertanyaan_kunci'])): ?><p class="fw-semibold"><?= e($kasus['pertanyaan_kunci']) ?></p><?php endif; ?>
      <h3 class="h6 mt-3">Latar belakang</h3><div><?= teks($kasus['latar_belakang']) ?></div>
      <?php if (!empty($kasus['referensi_data'])): ?><h3 class="h6 mt-3">Data referensi</h3><div class="small"><?= teks($kasus['referensi_data']) ?></div><?php endif; ?>
      <?php if ($klausul): ?><h3 class="h6 mt-3">Klausul Joint Resolution</h3>
        <ol class="small mb-0"><?php foreach ($klausul as $k): ?><li><strong><?= e($k['judul']) ?></strong> — <?= e($k['deskripsi']) ?></li><?php endforeach; ?></ol><?php endif; ?>
    </div>
  </section>

  <!-- ===== Argumen ===== -->
  <section class="panel" id="p-argumen">
    <div class="card-cr p-3 mb-3 d-none" id="argForm">
      <h2 class="h6">Tulis argumen (Claim – Evidence – Value)</h2>
      <p class="small mut mb-2">Ketiga kolom wajib diisi. Total kata <strong id="argRange"></strong>. Anda sudah mengirim <strong id="argMine">0</strong> dari maks <?= (int)$cfg['maxArgumen'] ?> argumen.</p>
      <label class="form-label small fw-bold">Claim <span class="fw-normal mut">— posisi/klaim Anda atas klausul</span></label>
      <textarea id="argClaim" rows="3" class="form-control mb-2" maxlength="2500"></textarea>
      <label class="form-label small fw-bold">Evidence <span class="fw-normal mut">— data kasus, rujukan hukum, alasan</span></label>
      <textarea id="argEvidence" rows="5" class="form-control mb-2" maxlength="4000"></textarea>
      <label class="form-label small fw-bold">National Value Alignment <span class="fw-normal mut">— kaitan eksplisit dengan Pancasila/konstitusi</span></label>
      <textarea id="argValue" rows="3" class="form-control mb-2" maxlength="2500"></textarea>
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-gold" id="argSend">Kirim argumen</button>
        <span class="wc" id="argWc">0 kata</span>
      </div>
      <div class="small text-danger mt-2" id="argErr"></div>
    </div>
    <div class="alert alert-secondary small d-none" id="argLocked"></div>
    <div class="filter-chips" id="argFilter"></div>
    <div id="argFeed"></div>
    <div class="mut small" id="argEmpty">Belum ada argumen.</div>
  </section>

  <!-- ===== Uji silang ===== -->
  <section class="panel" id="p-uji">
    <div class="card-cr p-3 mb-3 d-none" id="qForm">
      <h2 class="h6">Ajukan pertanyaan uji silang</h2>
      <div class="row g-2 mb-2">
        <div class="col-md-5"><label class="form-label small">Peran yang dituju</label><select id="qTarget" class="form-select"></select></div>
        <div class="col-md-7"><label class="form-label small">Argumen yang dirujuk (opsional)</label><select id="qParent" class="form-select"><option value="">— tidak merujuk argumen tertentu —</option></select></div>
      </div>
      <textarea id="qText" rows="3" class="form-control mb-2" placeholder="Pertanyaan Anda (maks <?= (int)$cfg['pertanyaanMax'] ?> kata)"></textarea>
      <div class="d-flex align-items-center gap-3"><button class="btn btn-gold" id="qSend">Kirim pertanyaan</button><span class="wc" id="qWc">0 kata</span></div>
      <div class="small text-danger mt-2" id="qErr"></div>
    </div>
    <div class="alert alert-secondary small d-none" id="qLocked"></div>
    <div class="filter-chips" id="qFilter"></div>
    <div id="qFeed"></div>
    <div class="mut small" id="qEmpty">Belum ada pertanyaan.</div>
  </section>

  <!-- ===== Nilai (muncul setelah dosen mempublikasikan) ===== -->
  <section class="panel" id="p-nilai">
    <div id="nilaiBox"></div>
  </section>

  <!-- ===== Negosiasi & Joint Resolution ===== -->
  <section class="panel" id="p-nego">
    <div class="row g-3">
      <div class="col-lg-7">
        <h2 class="h6">Joint Resolution <span class="mut fw-normal" id="resHint"></span></h2>
        <div id="resSyarat" class="small mb-2"></div>
        <div id="klausulBox"></div>
        <div class="card-cr p-3 mb-3 d-none" id="sikapBox">
          <h3 class="h6">Sikap peran Anda terhadap resolusi (delegasi)</h3>
          <div class="d-flex gap-3 mb-2">
            <label><input type="radio" name="sikap" value="setuju"> Setuju</label>
            <label><input type="radio" name="sikap" value="keberatan"> Keberatan beralasan</label>
          </div>
          <textarea id="sikapCatatan" rows="2" class="form-control mb-2" placeholder="Catatan / alasan keberatan"></textarea>
          <button class="btn btn-sm btn-navy" id="sikapSend">Simpan sikap peran</button>
        </div>
        <div class="card-cr p-3 mb-3"><h3 class="h6">Sikap tiap peran</h3><div id="sikapAll" class="small">Belum ada.</div></div>
      </div>
      <div class="col-lg-5">
        <h2 class="h6">Panel negosiasi <span class="mut fw-normal">(maks <?= (int)$cfg['negMax'] ?> karakter)</span></h2>
        <div class="neg-log mb-2" id="negLog"></div>
        <div id="negForm" class="d-none">
          <div class="input-group">
            <input id="negText" class="form-control" maxlength="<?= (int)$cfg['negMax'] ?>" placeholder="Tulis masukan untuk semua peserta…">
            <button class="btn btn-gold" id="negSend">Kirim</button>
          </div>
          <div class="small mut mt-1"><span id="negCount">0</span>/<?= (int)$cfg['negMax'] ?></div>
        </div>
        <div class="alert alert-secondary small d-none mt-2" id="negLocked"></div>
        <div class="small mt-3"><strong>Delegasi:</strong> <span id="delegList" class="mut">—</span></div>
      </div>
    </div>
  </section>
</div>

<?php
push_script('<script>window.ROOM = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>');
push_script('<script src="' . e(asset('js/room.js')) . '"></script>');
?>
