<?php
/** @var array $stats @var array $personas @var array $byKategori @var array $apiStats @var bool $stubMode */
$ov = $apiStats['overall'] ?? [];
?>
<h1 class="h4 mb-3">Ringkasan</h1>

<?php if ($stubMode): ?>
<div class="alert alert-warning py-2">
  <strong>Mode DEMO / STUB aktif.</strong> Claude API key belum dikonfigurasi (<code>config/config.local.php</code>).
  Jawaban persona, moderasi, dan klasifikasi memakai respons tiruan.
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Warga terdaftar', $stats['warga'], $stats['warga_verified'] . ' terverifikasi'],
    ['Percakapan', $stats['percakapan'], ''],
    ['Aspirasi masuk', $stats['aspirasi'], $stats['aspirasi_lolos'] . ' lolos · ' . $stats['aspirasi_tahan'] . ' ditahan'],
    ['Panggilan API', (int)($ov['calls'] ?? 0), (int)($ov['errors'] ?? 0) . ' error'],
  ];
  foreach ($cards as $c): ?>
  <div class="col-6 col-lg-3">
    <div class="cp-stat h-100">
      <div class="text-muted small text-uppercase"><?= e($c[0]) ?></div>
      <div class="n"><?= number_format((int)$c[1]) ?></div>
      <?php if ($c[2]): ?><div class="small text-muted"><?= e($c[2]) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="cp-stat">
      <h6 class="text-uppercase small text-muted mb-3">Aspirasi per kategori (lolos)</h6>
      <?php $mx = max($byKategori) ?: 1; foreach ($byKategori as $k => $v): ?>
      <div class="cp-bar-row">
        <div class="small text-capitalize"><?= e($k) ?></div>
        <div class="cp-bar" style="width:<?= (int)round($v/$mx*100) ?>%"></div>
        <div class="small text-end"><?= $v ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="cp-stat">
      <h6 class="text-uppercase small text-muted mb-3">Persona &amp; knowledge base</h6>
      <table class="table table-sm mb-0">
        <tbody>
        <?php foreach ($personas as $p): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($p['nama']) ?></div>
            <div class="small text-muted"><?= e($p['partai']) ?></div>
          </td>
          <td class="text-center"><?= (int)$p['chunk_count'] ?> chunk</td>
          <td class="text-end">
            <?= (int)$p['status_aktif'] === 1 ? '<span class="badge bg-success">aktif</span>' : '<span class="badge bg-secondary">nonaktif</span>' ?>
            <a class="btn btn-sm btn-outline-primary ms-1" href="<?= url('/admin/persona/' . $p['id'] . '/knowledge') ?>">KB</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="cp-stat mt-3">
  <h6 class="text-uppercase small text-muted mb-3">Biaya API — estimasi token</h6>
  <div class="row">
    <div class="col-md-3"><div class="text-muted small">Total panggilan</div><div class="fw-bold"><?= number_format((int)($ov['calls'] ?? 0)) ?></div></div>
    <div class="col-md-3"><div class="text-muted small">Input token</div><div class="fw-bold"><?= number_format((int)($ov['in_tok'] ?? 0)) ?></div></div>
    <div class="col-md-3"><div class="text-muted small">Output token</div><div class="fw-bold"><?= number_format((int)($ov['out_tok'] ?? 0)) ?></div></div>
    <div class="col-md-3"><div class="text-muted small">Rata-rata latensi</div><div class="fw-bold"><?= number_format((float)($ov['avg_ms'] ?? 0)) ?> ms</div></div>
  </div>
  <a href="<?= url('/admin/api-log') ?>" class="btn btn-sm btn-outline-secondary mt-3">Detail log</a>
</div>
