<?php
/** @var array $persona @var array $chunks @var ?string $success */
?>
<div class="d-flex justify-content-between align-items-center mb-1">
  <h1 class="h4 mb-0">Knowledge Base — <?= e($persona['nama']) ?></h1>
  <a href="<?= url('/admin/persona') ?>" class="btn btn-sm btn-link">← daftar persona</a>
</div>
<p class="text-muted small">Retrieval Fase 1 memakai pencarian leksikal (TF-IDF) atas teks di bawah. Tulis potongan pendek &amp; fokus (1 topik per potongan) beserta sumbernya.</p>
<?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>

<div class="cp-stat mb-3">
  <div class="fw-semibold mb-2">Tambah potongan</div>
  <form method="post" action="<?= url('/admin/persona/' . $persona['id'] . '/knowledge') ?>" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <select name="sumber" class="form-select">
        <?php foreach (['profil','pernyataan','isu','medsos','berita','lainnya'] as $s): ?>
          <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-9"><input name="judul" class="form-control" placeholder="Judul singkat"></div>
    <div class="col-12"><textarea name="teks_chunk" rows="4" class="form-control" placeholder="Isi fakta / kutipan…" required></textarea></div>
    <div class="col-md-9"><input name="referensi" class="form-control" placeholder="Sumber / URL referensi"></div>
    <div class="col-md-3 d-grid"><button class="btn btn-cp-navy">Tambah</button></div>
  </form>
</div>

<?php foreach ($chunks as $c): ?>
<div class="cp-stat mb-2">
  <form method="post" action="<?= url('/admin/knowledge/' . $c['id']) ?>" class="row g-2">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <select name="sumber" class="form-select form-select-sm">
        <?php foreach (['profil','pernyataan','isu','medsos','berita','lainnya'] as $s): ?>
          <option value="<?= $s ?>" <?= $c['sumber'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-7"><input name="judul" class="form-control form-control-sm" value="<?= e($c['judul']) ?>"></div>
    <div class="col-md-2 form-check ms-2 pt-1">
      <input class="form-check-input" type="checkbox" name="is_aktif" id="ak<?= $c['id'] ?>" <?= (int)$c['is_aktif'] === 1 ? 'checked' : '' ?>>
      <label class="form-check-label small" for="ak<?= $c['id'] ?>">aktif</label>
    </div>
    <div class="col-12"><textarea name="teks_chunk" rows="3" class="form-control form-control-sm"><?= e($c['teks_chunk']) ?></textarea></div>
    <div class="col-md-9"><input name="referensi" class="form-control form-control-sm" value="<?= e($c['referensi']) ?>" placeholder="Referensi"></div>
    <div class="col-md-3 d-flex gap-1 justify-content-end">
      <button class="btn btn-sm btn-outline-primary" name="save" value="1">Simpan</button>
      <button class="btn btn-sm btn-outline-danger" name="delete" value="1" onclick="return confirm('Hapus potongan ini?')">Hapus</button>
    </div>
  </form>
</div>
<?php endforeach; ?>
<?php if (!$chunks): ?><p class="text-muted">Belum ada potongan knowledge base.</p><?php endif; ?>
