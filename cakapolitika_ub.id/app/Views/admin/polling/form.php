<?php /** @var array $q @var array $opsi @var array $kategori @var int $jawaban @var ?string $success */ ?>
<div class="d-flex justify-content-between align-items-center mb-1">
  <h1 class="h4 mb-0">Pertanyaan Poll — <?= e($q['judul']) ?></h1>
  <a href="<?= url('/admin/polling') ?>" class="btn btn-sm btn-link">← daftar pertanyaan</a>
</div>
<p class="text-muted small"><?= (int)$jawaban ?> jawaban warga tercatat untuk pertanyaan ini.</p>
<?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>

<div class="cp-stat mb-3" style="max-width:820px">
  <div class="fw-semibold mb-2">Detail pertanyaan</div>
  <form method="post" action="<?= url('/admin/polling/' . $q['id']) ?>" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-md-7"><label class="form-label small mb-0">Judul *</label><input name="judul" class="form-control" value="<?= e($q['judul']) ?>" required></div>
    <div class="col-md-3">
      <label class="form-label small mb-0">Kategori</label>
      <select name="kategori" class="form-select">
        <option value="">—</option>
        <?php foreach ($kategori as $k): ?><option value="<?= e($k) ?>" <?= $q['kategori'] === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label small mb-0">Urutan</label><input name="urutan" type="number" class="form-control" value="<?= (int)$q['urutan'] ?>"></div>
    <div class="col-12"><label class="form-label small mb-0">Deskripsi / petunjuk</label><input name="deskripsi" class="form-control" value="<?= e($q['deskripsi']) ?>"></div>
    <div class="col-md-4">
      <label class="form-label small mb-0">Tipe</label>
      <select name="tipe" id="tipeSel" class="form-select">
        <option value="ganda" <?= $q['tipe'] === 'ganda' ? 'selected' : '' ?>>Pilihan Ganda (pilih 1)</option>
        <option value="checklist" <?= $q['tipe'] === 'checklist' ? 'selected' : '' ?>>Checklist (pilih beberapa)</option>
      </select>
      <div class="form-text">Alur chat Fase 1 baru mendukung Pilihan Ganda.</div>
    </div>
    <div class="col-md-3" id="maxWrap">
      <label class="form-label small mb-0">Maks. pilihan</label>
      <input name="max_pilihan" type="number" min="1" class="form-control" value="<?= (int)$q['max_pilihan'] ?>">
    </div>
    <div class="col-md-3 form-check ms-2 mt-4">
      <input class="form-check-input" type="checkbox" name="is_aktif" id="qa" <?= (int)$q['is_aktif'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="qa">Aktif</label>
    </div>
    <div class="col-12 mt-2"><button class="btn btn-cp-navy">Simpan pertanyaan</button></div>
  </form>
</div>

<div class="cp-stat mb-3" style="max-width:820px">
  <div class="fw-semibold mb-2">Tambah opsi jawaban</div>
  <form method="post" action="<?= url('/admin/polling/' . $q['id'] . '/opsi') ?>" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-9"><input name="label" class="form-control" placeholder="Teks pernyataan / opsi" required></div>
    <div class="col-1"><input name="urutan" type="number" class="form-control" placeholder="#" value="<?= count($opsi) + 1 ?>"></div>
    <div class="col-2 d-grid"><button class="btn btn-cp-navy">Tambah</button></div>
  </form>
</div>

<?php foreach ($opsi as $o): ?>
<div class="cp-stat mb-2" style="max-width:820px">
  <form method="post" action="<?= url('/admin/poll-opsi/' . $o['id']) ?>" class="row g-2 align-items-center">
    <?= csrf_field() ?>
    <div class="col-8"><input name="label" class="form-control form-control-sm" value="<?= e($o['label']) ?>"></div>
    <div class="col-1"><input name="urutan" type="number" class="form-control form-control-sm" value="<?= (int)$o['urutan'] ?>"></div>
    <div class="col-1 form-check ms-2">
      <input class="form-check-input" type="checkbox" name="is_aktif" id="oa<?= $o['id'] ?>" <?= (int)$o['is_aktif'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label small" for="oa<?= $o['id'] ?>">aktif</label>
    </div>
    <div class="col-2 d-flex gap-1 justify-content-end">
      <button class="btn btn-sm btn-outline-primary" name="save" value="1">Simpan</button>
      <button class="btn btn-sm btn-outline-danger" name="delete" value="1" onclick="return confirm('Hapus opsi ini?')">Hapus</button>
    </div>
  </form>
</div>
<?php endforeach; ?>
<?php if (!$opsi): ?><p class="text-muted">Belum ada opsi jawaban. Tambahkan minimal 2 agar pertanyaan tampil ke warga.</p><?php endif; ?>

<?php ob_start(); ?>
<script>
(function () {
  var sel = document.getElementById('tipeSel'), wrap = document.getElementById('maxWrap');
  function sync() { wrap.style.display = sel.value === 'checklist' ? '' : 'none'; }
  sel.addEventListener('change', sync); sync();
})();
</script>
<?php push_script(ob_get_clean()); ?>
