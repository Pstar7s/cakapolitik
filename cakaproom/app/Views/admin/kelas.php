<h1 class="h4 mb-3">Kelas</h1>
<form method="post" action="<?= url('/admin/kelas') ?>" class="card-cr p-3 mb-3 row g-2 align-items-end"><?= csrf_field() ?>
  <div class="col-md-4"><label class="form-label small">Nama kelas</label><input name="nama_kelas" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label small">Mata kuliah</label><input name="mata_kuliah" class="form-control"></div>
  <div class="col-md-3"><label class="form-label small">Dosen pengampu</label>
    <select name="dosen_id" class="form-select"><option value="">—</option><?php foreach ($dosen as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['nama']) ?> (<?= e($d['email']) ?>)</option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-navy w-100">Buat kelas</button></div>
</form>
<div class="card-cr p-2 tbl-wrap"><table class="tbl">
<tr><th>Kelas</th><th>Mata kuliah</th><th>Dosen</th><th>Mahasiswa</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['nama_kelas']) ?></td><td><?= e($r['mata_kuliah']) ?></td><td><?= e($r['dosen'] ?? '—') ?></td><td><?= (int)$r['jml'] ?></td>
<td><a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/kelas/' . (int)$r['id']) ?>">Roster</a></td></tr>
<?php endforeach; ?></table></div>
