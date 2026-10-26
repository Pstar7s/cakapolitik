<a href="<?= url('/admin/kelas') ?>" class="small">&larr; Kelas</a>
<h1 class="h4 mb-1"><?= e($kelas['nama_kelas']) ?></h1>
<p class="mut"><?= e($kelas['mata_kuliah']) ?> · <?= count($mhs) ?> mahasiswa</p>
<form method="post" action="<?= url('/admin/kelas/' . (int)$kelas['id'] . '/import') ?>" enctype="multipart/form-data" class="card-cr p-3 mb-3"><?= csrf_field() ?>
  <h2 class="h6">Impor roster (CSV)</h2>
  <p class="small mut mb-2">Kolom: <code>nama,email,nim</code> (baris pertama boleh header). Pemisah koma atau titik koma. Akun baru dibuat otomatis; login memakai email + OTP.</p>
  <div class="row g-2">
    <div class="col-md-5"><input type="file" name="csv" accept=".csv,text/csv,text/plain" class="form-control"></div>
    <div class="col-md-5"><textarea name="teks" rows="2" class="form-control" placeholder="…atau tempel baris CSV di sini"></textarea></div>
    <div class="col-md-2"><button class="btn btn-navy w-100">Impor</button></div>
  </div>
</form>
<div class="card-cr p-2 tbl-wrap"><table class="tbl">
<tr><th>NIM</th><th>Nama</th><th>Email</th></tr>
<?php foreach ($mhs as $m): ?><tr><td><?= e($m['nim']) ?></td><td><?= e($m['nama']) ?></td><td><?= e($m['email']) ?></td></tr><?php endforeach; ?>
</table></div>
