<h1 class="h4 mb-3">Pengguna</h1>
<form method="post" action="<?= url('/admin/pengguna') ?>" class="card-cr p-3 mb-3 row g-2 align-items-end"><?= csrf_field() ?>
  <div class="col-md-3"><label class="form-label small">Nama</label><input name="nama" class="form-control" required></div>
  <div class="col-md-3"><label class="form-label small">Email</label><input name="email" type="email" class="form-control" required></div>
  <div class="col-md-2"><label class="form-label small">NIM (opsional)</label><input name="nim" class="form-control"></div>
  <div class="col-md-2"><label class="form-label small">Peran</label>
    <select name="role" class="form-select"><option value="dosen">dosen</option><option value="mahasiswa">mahasiswa</option><option value="admin">admin</option></select></div>
  <div class="col-md-2"><button class="btn btn-navy w-100">Tambah</button></div>
</form>
<div class="card-cr p-2 tbl-wrap"><table class="tbl">
<tr><th>Nama</th><th>Email</th><th>NIM</th><th>Peran</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['nama']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['nim']) ?></td><td><?= e($r['role']) ?></td>
<td><?= $r['is_active'] ? 'aktif' : '<span class="text-danger">nonaktif</span>' ?></td>
<td><form method="post" action="<?= url('/admin/pengguna/' . (int)$r['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr>
<?php endforeach; ?></table></div>
