<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Kasus</h1><a class="btn btn-navy" href="<?= url('/admin/kasus/baru') ?>">+ Kasus baru</a>
</div>
<div class="card-cr p-2 tbl-wrap"><table class="tbl">
<tr><th>Judul</th><th>Mata kuliah</th><th>Status</th><th>Rubrik</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['judul']) ?></td><td><?= e($r['mata_kuliah']) ?></td>
<td><span class="status-pill <?= $r['status'] === 'siap' ? 'berjalan' : 'lobby' ?>"><?= e($r['status']) ?></span></td>
<td><?= e($r['rubrik_status']) ?></td>
<td class="d-flex gap-1">
  <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/kasus/' . (int)$r['id'] . '/edit') ?>">Edit</a>
  <form method="post" action="<?= url('/admin/kasus/' . (int)$r['id'] . '/hapus') ?>" onsubmit="return confirm('Hapus kasus ini?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button></form>
</td></tr>
<?php endforeach; ?></table></div>
