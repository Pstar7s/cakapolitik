<?php
/** @var ?array $persona @var array $dapils */
$p = $persona ?? [];
$isEdit = !empty($p['id']);
$action = $isEdit ? url('/admin/persona/' . $p['id']) : url('/admin/persona');
function v($p, $k, $d = '') { return e($p[$k] ?? $d); }
?>
<h1 class="h4 mb-3"><?= $isEdit ? 'Edit' : 'Tambah' ?> Persona</h1>

<form method="post" action="<?= $action ?>" class="cp-stat" style="max-width:820px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small">Nama *</label><input name="nama" class="form-control" value="<?= v($p,'nama') ?>" required></div>
    <div class="col-md-3"><label class="form-label small">Partai</label><input name="partai" class="form-control" value="<?= v($p,'partai') ?>"></div>
    <div class="col-md-3"><label class="form-label small">Perolehan suara</label><input name="perolehan_suara" type="number" class="form-control" value="<?= v($p,'perolehan_suara') ?>"></div>
    <div class="col-md-8"><label class="form-label small">Jabatan</label><input name="jabatan" class="form-control" value="<?= v($p,'jabatan') ?>"></div>
    <div class="col-md-4">
      <label class="form-label small">Dapil</label>
      <select name="dapil_id" class="form-select">
        <option value="">—</option>
        <?php foreach ($dapils as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= (int)($p['dapil_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['nama_dapil']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><label class="form-label small">URL foto</label><input name="foto_url" class="form-control" value="<?= v($p,'foto_url') ?>" placeholder="https://… atau assets/img/persona/nama.jpg"></div>
    <div class="col-12"><label class="form-label small">Deskripsi singkat</label><textarea name="deskripsi_singkat" rows="3" class="form-control"><?= v($p,'deskripsi_singkat') ?></textarea></div>
    <div class="col-12">
      <label class="form-label small">Pesan pembuka <span class="text-muted">(sapaan scripted saat warga memilih persona — bukan AI)</span></label>
      <textarea name="pesan_pembuka" rows="6" class="form-control" placeholder="Kosongkan bila tidak ingin ada sapaan pembuka."><?= v($p,'pesan_pembuka') ?></textarea>
      <div class="form-text">Boleh pakai <code>**tebal**</code>, <code>_miring_</code>, dan baris kosong untuk paragraf baru.</div>
    </div>
    <div class="col-12"><label class="form-label small">Gaya bicara (dipakai di system prompt)</label><textarea name="gaya_bicara" rows="2" class="form-control"><?= v($p,'gaya_bicara') ?></textarea></div>
    <div class="col-12"><label class="form-label small">Instruksi tambahan (opsional)</label><textarea name="system_prompt_extra" rows="2" class="form-control"><?= v($p,'system_prompt_extra') ?></textarea></div>
    <div class="col-12 form-check ms-2">
      <input class="form-check-input" type="checkbox" name="status_aktif" id="sa" <?= !$isEdit || (int)($p['status_aktif'] ?? 0) === 1 ? 'checked' : '' ?>>
      <label class="form-check-label" for="sa">Aktif (tampil ke warga)</label>
    </div>
  </div>
  <div class="mt-3">
    <button class="btn btn-cp-navy"><?= $isEdit ? 'Simpan' : 'Buat &amp; isi knowledge' ?></button>
    <a href="<?= url('/admin/persona') ?>" class="btn btn-link">Batal</a>
  </div>
</form>
