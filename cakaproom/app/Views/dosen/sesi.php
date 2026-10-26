<?php /** @var array $sesi,$kasus,$kelas,$cfg,$durasi */ ?>
<div class="room-top">
  <div class="inner">
    <a href="<?= url('/dosen') ?>" class="text-white-50 text-decoration-none small">&larr;</a>
    <div class="room-title"><?= e($sesi['judul'] ?: $kasus['judul']) ?> · <?= e($kelas['nama_kelas']) ?></div>
    <span class="fase-pill" id="fasePill">…</span>
    <div class="timer" id="timer">--:--</div>
    <span class="conn" id="conn">menghubungkan…</span>
  </div>
</div>
<div class="room-tabs" id="tabs">
  <button data-tab="kontrol" class="active">Kontrol</button>
  <button data-tab="peserta">Peserta &amp; Delegasi</button>
  <button data-tab="feed">Feed</button>
  <button data-tab="nilai">Penilaian</button>
  <button data-tab="individu">Nilai Individual</button>
</div>

<div class="room-main">

  <section class="panel active" id="p-kontrol">
    <div class="row g-2 mb-3" id="tiles">
      <div class="col-4 col-md-2"><div class="stat-tile"><div class="n" id="sOnline">0</div><div class="small mut">online</div></div></div>
      <div class="col-4 col-md-2"><div class="stat-tile"><div class="n" id="sJoin">0</div><div class="small mut">bergabung</div></div></div>
      <div class="col-4 col-md-2"><div class="stat-tile"><div class="n" id="sRoster">0</div><div class="small mut">roster kelas</div></div></div>
      <div class="col-12 col-md-6"><div class="stat-tile"><div class="small mut mb-1">Fase aktif</div><div class="fw-bold" id="sFase">—</div><div class="small mut" id="sStatus"></div></div></div>
    </div>

    <div class="card-cr p-3 mb-3">
      <h2 class="h6">Kontrol sesi</h2>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-navy" id="bBagi">1 · Bagi Peran</button>
        <button class="btn btn-gold" id="bMulai">2 · Mulai sesi (Fase 1)</button>
        <button class="btn btn-outline-secondary" id="bJeda">Jeda</button>
        <button class="btn btn-outline-secondary" id="bLanjut">Lanjut</button>
        <button class="btn btn-navy" id="bNext">Fase berikutnya ▶</button>
        <button class="btn btn-outline-danger" id="bAkhiri">Akhiri sesi</button>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small mut">Waktu fase:</span>
        <button class="btn btn-sm btn-outline-secondary" data-add="-60">−1 mnt</button>
        <button class="btn btn-sm btn-outline-secondary" data-add="60">+1 mnt</button>
        <button class="btn btn-sm btn-outline-secondary" data-add="300">+5 mnt</button>
      </div>
      <div class="small mut mt-2">Urutan: Lobby → Argumen → Uji silang → Negosiasi &amp; Joint Resolution → Penutupan. Timer memakai waktu server; hanya dosen yang memindahkan fase.</div>
      <div class="small text-danger mt-1" id="ctrlErr"></div>
    </div>

    <div class="row g-2" id="peranCols"></div>
  </section>

  <section class="panel" id="p-peserta">
    <div class="card-cr p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Peserta (<span id="pJumlah">0</span>)</h2>
        <span class="small mut">Delegasi: <?= (int)config('sesi.delegasi_per_peran') ?> per peran · mode <?= e(config('sesi.delegasi_mode')) ?></span></div>
      <div class="tbl-wrap"><table class="tbl" id="pTbl"><tr><th>Nama</th><th>NIM</th><th>Peran</th><th>Delegasi</th></tr></table></div>
    </div>
  </section>

  <section class="panel" id="p-feed">
    <div class="d-flex justify-content-between mb-2"><h2 class="h6 mb-0">Feed langsung (terbaru di atas)</h2>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('/dosen/sesi/' . (int)$sesi['id'] . '/transkrip') ?>">Transkrip lengkap</a></div>
    <div id="dFeed"></div>
  </section>

  <section class="panel" id="p-nilai">
    <div class="ai-label mb-3">Skor awal AI (saran) — keputusan akhir ada pada dosen.
      <span id="rubrikInfo"><?= $kasus['rubrik_status'] === 'sementara' ? ' Rubrik masih berstatus SEMENTARA (draf, belum disetujui dosen).' : '' ?></span></div>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <button class="btn btn-gold" id="bSkor">Hitung Skor Awal AI</button>
      <a class="btn btn-outline-secondary" href="<?= url('/dosen/sesi/' . (int)$sesi['id'] . '/export.csv') ?>">Ekspor CSV nilai kelompok</a>
      <button class="btn btn-outline-secondary" id="bSnap">Simpan snapshot Joint Resolution</button>
    </div>
    <div class="card-cr p-3 mb-3">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <button class="btn btn-navy" id="bPublik" disabled>Publikasikan nilai ke mahasiswa</button>
        <span class="small" id="publikInfo">Nilai belum dipublikasikan.</span>
      </div>
      <div class="small mut mt-2">Mahasiswa melihat nilai kelompok perannya (per indikator + catatan dosen) dan nilai individunya sendiri. Skor AI, nilai peran lain, nilai teman, dan <em>alasan</em> penyesuaian individual tidak ditampilkan. Tombol aktif setelah keempat kelompok ditetapkan; dapat ditarik kembali kapan saja.</div>
    </div>
    <div class="small text-danger mb-2" id="nilaiErr"></div>
    <div class="row g-3" id="scoreCards"></div>
  </section>

  <section class="panel" id="p-individu">
    <div class="card-cr p-3">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h2 class="h6 mb-0">Penyesuaian nilai individual</h2>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/dosen/sesi/' . (int)$sesi['id'] . '/export.csv?level=individu') ?>">Ekspor CSV per mahasiswa</a>
      </div>
      <p class="small mut">Default nilai individu = nilai final kelompok. Statistik partisipasi hanya informasi — tidak otomatis mengubah nilai. Alasan wajib bila nilai berbeda dari nilai kelompok; setiap perubahan tercatat di audit log.
        <?= $cfg['batas'] !== null ? 'Batas penyesuaian: ±' . e($cfg['batas']) . ' poin.' : '' ?></p>
      <div class="tbl-wrap"><table class="tbl" id="iTbl"></table></div>
    </div>
  </section>
</div>

<?php
push_script('<script>window.DOSEN = ' . json_encode($cfg + ['csrf' => csrf_token()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>');
push_script('<script src="' . e(asset('js/dosen.js')) . '"></script>');
?>
