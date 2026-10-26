<a href="<?= url('/dosen') ?>" class="small">&larr; Sesi</a>
<h1 class="h4 mb-3">Sesi baru</h1>
<form method="post" action="<?= url('/dosen/sesi') ?>" class="card-cr p-3"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Kelas</label>
      <select name="kelas_id" class="form-select" required><?php foreach ($kelas as $k): ?><option value="<?= (int)$k['id'] ?>"><?= e($k['nama_kelas']) ?> (<?= (int)$k['jml'] ?> mhs)</option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Kasus</label>
      <select name="kasus_id" class="form-select" required><?php foreach ($kasus as $k): ?><option value="<?= (int)$k['id'] ?>" <?= $k['status'] !== 'siap' ? 'disabled' : '' ?>><?= e($k['judul']) ?><?= $k['status'] !== 'siap' ? ' (draft)' : '' ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Judul sesi (opsional)</label><input name="judul" class="form-control"></div>
    <div class="col-12"><label class="form-label">Durasi fase (menit) <span class="mut small">— total default 60 menit; dapat dikoreksi dosen saat sesi berjalan</span></label>
      <div class="row g-2">
      <?php foreach ($durasi as $f => $d): ?>
        <div class="col-6 col-md"><div class="small mut"><?= e(FASE_LABEL[$f] ?? $f) ?></div><input type="number" step="0.5" min="0.5" name="menit_<?= e($f) ?>" class="form-control" value="<?= e($d / 60) ?>"></div>
      <?php endforeach; ?></div></div>
    <div class="col-md-3"><label class="form-label">Kata minimum (total 3 kolom)</label><input type="number" name="word_min" class="form-control" value="<?= (int)$wmin ?>"></div>
    <div class="col-md-3"><label class="form-label">Kata maksimum</label><input type="number" name="word_max" class="form-control" value="<?= (int)$wmax ?>"></div>
  </div>
  <button class="btn btn-gold mt-3">Buat sesi &amp; buka lobby</button>
</form>
