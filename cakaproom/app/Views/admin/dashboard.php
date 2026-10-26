<h1 class="h4 mb-3">Ringkasan</h1>
<div class="row g-3 mb-4">
<?php foreach (['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'kelas' => 'Kelas', 'kasus' => 'Kasus siap', 'sesi' => 'Sesi'] as $k => $l): ?>
  <div class="col-6 col-md"><div class="stat-tile"><div class="n"><?= (int)$n[$k] ?></div><div class="small mut"><?= e($l) ?></div></div></div>
<?php endforeach; ?>
</div>
<div class="card-cr p-3 mb-3">
  <h2 class="h6">Pemakaian Claude API (semua sesi)</h2>
  <p class="mb-0 small"><?= (int)$biaya['panggilan'] ?> panggilan · <?= number_format((int)$biaya['masuk']) ?> token masuk · <?= number_format((int)$biaya['keluar']) ?> token keluar.
  <span class="mut">Mode stub tidak mencatat token. Hitung biaya dari tarif model aktif.</span></p>
</div>
<div class="d-flex gap-2 flex-wrap">
  <a class="btn btn-navy" href="<?= url('/admin/kelas') ?>">Kelola kelas &amp; roster</a>
  <a class="btn btn-navy" href="<?= url('/admin/kasus') ?>">Kelola kasus</a>
  <a class="btn btn-outline-secondary" href="<?= url('/admin/pengguna') ?>">Pengguna</a>
</div>
