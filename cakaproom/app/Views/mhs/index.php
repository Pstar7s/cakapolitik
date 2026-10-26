<h1 class="h4 mb-3">Sesi Saya</h1>
<?php if (!$sesi): ?>
  <div class="card-cr p-4 text-center mut">Belum ada sesi untuk kelas Anda. Tunggu dosen membuat sesi.</div>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($sesi as $s): ?>
  <div class="col-md-6">
    <div class="card-cr p-3 h-100">
      <div class="d-flex justify-content-between align-items-start mb-1">
        <strong><?= e($s['judul'] ?: $s['kasus']) ?></strong>
        <span class="status-pill <?= e($s['status']) ?>"><?= e($s['status']) ?></span>
      </div>
      <div class="small mut mb-2"><?= e($s['nama_kelas']) ?> · <?= e($s['kasus']) ?></div>
      <?php if (in_array($s['status'], ['lobby', 'berjalan'], true)): ?>
        <a class="btn btn-gold" href="<?= url('/sesi/' . (int)$s['id']) ?>">Masuk ruang sesi</a>
      <?php else: ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('/sesi/' . (int)$s['id']) ?>">Lihat hasil sesi</a>
        <?php if (!empty($s['nilai_publik'])): ?><span class="badge text-bg-success ms-1">Nilai tersedia</span><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
