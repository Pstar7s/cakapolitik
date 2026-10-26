<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Sesi &amp; Kelas</h1>
  <a class="btn btn-gold" href="<?= url('/dosen/sesi/baru') ?>">+ Sesi baru</a>
</div>

<h2 class="h6 mut text-uppercase">Kelas saya</h2>
<div class="row g-2 mb-4">
<?php foreach ($kelas as $k): ?>
  <div class="col-md-4"><div class="card-cr p-3"><strong><?= e($k['nama_kelas']) ?></strong><div class="small mut"><?= e($k['mata_kuliah']) ?> · <?= (int)$k['jml'] ?> mahasiswa</div></div></div>
<?php endforeach; ?>
<?php if (!$kelas): ?><div class="col-12 mut">Belum ada kelas yang ditugaskan. Hubungi admin.</div><?php endif; ?>
</div>

<h2 class="h6 mut text-uppercase">Sesi</h2>
<div class="card-cr p-2 tbl-wrap"><table class="tbl">
<tr><th>Sesi</th><th>Kelas</th><th>Status</th><th>Dibuat</th><th></th></tr>
<?php foreach ($sesi as $s): ?>
<tr><td><?= e($s['judul'] ?: $s['kasus']) ?></td><td><?= e($s['nama_kelas']) ?></td>
<td><span class="status-pill <?= e($s['status']) ?>"><?= e($s['status']) ?></span></td><td><?= e(tgl($s['created_at'])) ?></td>
<td><a class="btn btn-sm btn-navy" href="<?= url('/dosen/sesi/' . (int)$s['id']) ?>">Buka</a></td></tr>
<?php endforeach; ?>
<?php if (!$sesi): ?><tr><td colspan="5" class="mut">Belum ada sesi.</td></tr><?php endif; ?>
</table></div>
