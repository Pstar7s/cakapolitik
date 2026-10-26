<?php $k = $k ?? []; $isEdit = !empty($k['id']); ?>
<a href="<?= url('/admin/kasus') ?>" class="small">&larr; Kasus</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'Edit kasus' : 'Kasus baru' ?></h1>
<?php if (!empty($dipakai)): ?><div class="alert alert-warning small">Kasus ini dipakai <?= (int)$dipakai ?> sesi. Mengubah daftar klausul tidak mengubah sesi yang sudah dibuat; sebaiknya buat kasus baru bila klausul berubah.</div><?php endif; ?>
<form method="post" action="<?= url($isEdit ? '/admin/kasus/' . (int)$k['id'] : '/admin/kasus') ?>"><?= csrf_field() ?>
<div class="card-cr p-3 mb-3">
  <div class="row g-2">
    <div class="col-md-8"><label class="form-label">Judul</label><input name="judul" class="form-control" required value="<?= e($k['judul'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Mata kuliah</label><input name="mata_kuliah" class="form-control" value="<?= e($k['mata_kuliah'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">Tujuan pembelajaran</label><textarea name="tujuan" rows="3" class="form-control"><?= e($k['tujuan'] ?? '') ?></textarea></div>
    <div class="col-12"><label class="form-label">Latar belakang kasus</label><textarea name="latar_belakang" rows="8" class="form-control" required><?= e($k['latar_belakang'] ?? '') ?></textarea></div>
    <div class="col-12"><label class="form-label">Data referensi (hasil survei, tabel, rujukan hukum)</label><textarea name="referensi_data" rows="10" class="form-control"><?= e($k['referensi_data'] ?? '') ?></textarea></div>
    <div class="col-12"><label class="form-label">Pertanyaan kunci Joint Resolution</label><textarea name="pertanyaan_kunci" rows="2" class="form-control"><?= e($k['pertanyaan_kunci'] ?? '') ?></textarea></div>
  </div>
</div>
<div class="card-cr p-3 mb-3"><h2 class="h6">Brief 4 peran</h2>
<?php foreach (PERAN as $kode => $p): ?>
  <label class="form-label mt-2"><span class="badge-peran bg-<?= $kode ?>"><?= e($p['label']) ?></span></label>
  <textarea name="brief[<?= $kode ?>]" rows="9" class="form-control"><?= e($briefs[$kode] ?? '') ?></textarea>
<?php endforeach; ?></div>
<div class="card-cr p-3 mb-3"><h2 class="h6">Klausul Joint Resolution <span class="mut small">(urut = nomor klausul; kosongkan judul untuk menghapus)</span></h2>
<?php for ($i = 0; $i < 8; $i++): $kl = $klausul[$i] ?? null; ?>
  <div class="row g-2 mb-2"><div class="col-md-4"><input name="klausul_judul[]" class="form-control" placeholder="Klausul <?= $i + 1 ?> — judul" value="<?= e($kl['judul'] ?? '') ?>"></div>
  <div class="col-md-8"><input name="klausul_desk[]" class="form-control" placeholder="Yang harus diputuskan" value="<?= e($kl['deskripsi'] ?? '') ?>"></div></div>
<?php endfor; ?></div>
<div class="card-cr p-3 mb-3"><h2 class="h6">Rubrik penilaian (JSON) — dasar prompt skor AI</h2>
  <textarea name="rubrik_json" rows="12" class="form-control font-monospace small"><?= e($k['rubrik_json'] ?? '') ?></textarea>
  <div class="row g-2 mt-2">
    <div class="col-md-6"><label class="form-label small">Status rubrik</label><select name="rubrik_status" class="form-select">
      <option value="sementara" <?= ($k['rubrik_status'] ?? '') !== 'disetujui' ? 'selected' : '' ?>>sementara (draf, belum disetujui dosen)</option>
      <option value="disetujui" <?= ($k['rubrik_status'] ?? '') === 'disetujui' ? 'selected' : '' ?>>disetujui dosen</option></select></div>
    <div class="col-md-6"><label class="form-label small">Status kasus</label><select name="status" class="form-select">
      <option value="draft" <?= ($k['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>draft</option>
      <option value="siap" <?= ($k['status'] ?? '') === 'siap' ? 'selected' : '' ?>>siap dipakai sesi</option></select></div>
  </div>
</div>
<button class="btn btn-gold">Simpan kasus</button>
</form>
