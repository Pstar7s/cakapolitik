<?php /** @var array $pertanyaan @var array $kategori @var int $responden @var ?string $success */ ?>
<div class="d-flex justify-content-between align-items-center mb-1">
  <h1 class="h4 mb-0">Polling Aspirasi</h1>
  <a href="<?= url('/dashboard') ?>" target="_blank" class="btn btn-sm btn-link">Lihat grafik di Dashboard →</a>
</div>
<p class="text-muted small mb-3">
  Jajak pendapat ini disajikan BRONE saat warga menekan "Akhiri sesi".
  Total responden sejauh ini: <strong><?= number_format($responden) ?></strong> warga.
  <br>Fase 1: hanya tipe <strong>Pilihan Ganda</strong> (warga memilih 1 prioritas per pertanyaan).
</p>
<?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>

<div class="cp-stat mb-3">
  <div class="fw-semibold mb-2">Tambah pertanyaan</div>
  <form method="post" action="<?= url('/admin/polling') ?>" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-md-6"><input name="judul" class="form-control" placeholder="Judul bidang, mis. Pendidikan" required></div>
    <div class="col-md-3">
      <select name="kategori" class="form-select">
        <option value="">— kategori (opsional) —</option>
        <?php foreach ($kategori as $k): ?><option value="<?= e($k) ?>"><?= ucfirst($k) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><input name="urutan" type="number" class="form-control" placeholder="Urutan" value="<?= count($pertanyaan) + 1 ?>"></div>
    <div class="col-md-1 d-grid"><button class="btn btn-cp-navy">Tambah</button></div>
    <input type="hidden" name="tipe" value="ganda">
    <input type="hidden" name="is_aktif" value="1">
    <div class="col-12"><input name="deskripsi" class="form-control form-control-sm" placeholder="Deskripsi/petunjuk singkat (opsional)"></div>
  </form>
</div>

<div class="cp-stat">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>#</th><th>Pertanyaan</th><th>Kategori</th><th>Tipe</th><th class="text-center">Opsi</th><th class="text-center">Jawaban</th><th class="text-center">Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($pertanyaan as $q): ?>
        <tr>
          <td class="text-muted small"><?= (int)$q['urutan'] ?></td>
          <td class="fw-semibold"><?= e($q['judul']) ?><?php if ($q['deskripsi']): ?><br><span class="fw-normal text-muted small"><?= e($q['deskripsi']) ?></span><?php endif; ?></td>
          <td class="small text-capitalize"><?= e($q['kategori'] ?: '—') ?></td>
          <td><span class="badge bg-light text-dark border"><?= $q['tipe'] === 'checklist' ? 'Checklist (maks ' . (int)$q['max_pilihan'] . ')' : 'Pilihan Ganda' ?></span></td>
          <td class="text-center"><?= (int)$q['opsi_count'] ?></td>
          <td class="text-center"><?= (int)$q['jawaban_count'] ?></td>
          <td class="text-center"><?= (int)$q['is_aktif'] === 1 ? '<span class="badge bg-success">aktif</span>' : '<span class="badge bg-secondary">nonaktif</span>' ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/polling/' . $q['id']) ?>">Kelola opsi</a>
            <form method="post" action="<?= url('/admin/polling/' . $q['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Hapus pertanyaan ini beserta opsi &amp; jawabannya?')">
              <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pertanyaan): ?><tr><td colspan="8" class="text-muted">Belum ada pertanyaan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
