<?php
/** @var array $rows @var int $total @var int $page @var int $perPage
 *  @var array $filters @var array $personas @var array $kategoris @var ?string $success */
$pages = max(1, (int)ceil($total / $perPage));
$qs = fn(array $ov = []) => url('/admin/aspirasi?' . http_build_query(array_merge(array_filter($filters), $ov)));
?>
<h1 class="h4 mb-3">Aspirasi Masuk <span class="text-muted fs-6">(<?= number_format($total) ?>)</span></h1>
<?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>

<form class="row g-2 mb-3">
  <div class="col-md-3">
    <select name="kategori" class="form-select form-select-sm">
      <option value="">Semua kategori</option>
      <?php foreach ($kategoris as $k): ?><option value="<?= $k ?>" <?= $filters['kategori'] === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select name="status" class="form-select form-select-sm">
      <option value="">Semua status</option>
      <?php foreach (['lolos','ditahan','belum_dicek'] as $s): ?><option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select name="persona_id" class="form-select form-select-sm">
      <option value="">Semua persona</option>
      <?php foreach ($personas as $p): ?><option value="<?= $p['id'] ?>" <?= (string)$filters['persona_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3"><button class="btn btn-sm btn-cp-navy">Filter</button> <a href="<?= url('/admin/aspirasi') ?>" class="btn btn-sm btn-link">reset</a></div>
</form>

<div class="cp-stat">
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>Warga</th><th>Kepada</th><th>Kategori</th><th>Aspirasi</th><th>Status</th><th>Waktu</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td class="small">
            <?= $r['is_anonim'] ? '<em class="text-muted">Anonim</em>' : e($r['warga_nama']) ?><br>
            <span class="text-muted"><?= e(trim(($r['kecamatan'] ?? '') . ', ' . ($r['kab_kota'] ?? ''), ', ')) ?></span>
          </td>
          <td class="small"><?= e($r['persona_nama'] ?? '—') ?></td>
          <td><span class="badge bg-secondary text-capitalize"><?= e($r['kategori']) ?></span></td>
          <td class="small" style="max-width:320px"><?= e($r['isi_aspirasi']) ?>
            <?php if ($r['alasan_moderasi']): ?><br><span class="text-muted fst-italic">Moderasi: <?= e($r['alasan_moderasi']) ?></span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= e($r['status_moderasi']) ?>"><?= e($r['status_moderasi']) ?></span></td>
          <td class="small text-muted text-nowrap"><?= e(tgl($r['created_at'])) ?></td>
          <td class="text-nowrap">
            <form method="post" action="<?= url('/admin/aspirasi/' . $r['id'] . '/moderasi') ?>" class="d-flex gap-1">
              <?= csrf_field() ?>
              <input type="hidden" name="back_kategori" value="<?= e($filters['kategori']) ?>">
              <input type="hidden" name="back_status" value="<?= e($filters['status']) ?>">
              <input type="hidden" name="back_persona" value="<?= e($filters['persona_id']) ?>">
              <input type="hidden" name="back_page" value="<?= $page ?>">
              <select name="status" class="form-select form-select-sm" style="width:auto">
                <?php foreach (['lolos','ditahan','belum_dicek'] as $s): ?><option value="<?= $s ?>" <?= $r['status_moderasi'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-primary">Set</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">Tidak ada aspirasi.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav><ul class="pagination pagination-sm">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $qs(['page' => $i]) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
