<?php /** @var array $rows @var array $stats */ ?>
<h1 class="h4 mb-3">Log &amp; Biaya API Claude</h1>

<div class="row g-2 mb-3">
<?php foreach (($stats['by_context'] ?? []) as $c): ?>
  <div class="col-6 col-md-3">
    <div class="cp-stat">
      <div class="small text-muted text-uppercase"><?= e($c['konteks'] ?: '—') ?></div>
      <div class="fw-bold"><?= number_format((int)$c['calls']) ?> panggilan</div>
      <div class="small text-muted">in <?= number_format((int)$c['in_tok']) ?> · out <?= number_format((int)$c['out_tok']) ?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="cp-stat">
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>#</th><th>Waktu</th><th>Konteks</th><th>Model</th><th>In</th><th>Out</th><th>ms</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td class="small text-nowrap"><?= e(tgl($r['created_at'])) ?></td>
          <td><span class="badge bg-secondary"><?= e($r['konteks'] ?: '—') ?></span></td>
          <td class="small"><?= e($r['model'] ?: '—') ?></td>
          <td><?= $r['input_tokens'] !== null ? (int)$r['input_tokens'] : '—' ?></td>
          <td><?= $r['output_tokens'] !== null ? (int)$r['output_tokens'] : '—' ?></td>
          <td><?= $r['latency_ms'] !== null ? (int)$r['latency_ms'] : '—' ?></td>
          <td><?= (int)$r['ok'] === 1 ? '<span class="text-success">OK</span>' : '<span class="text-danger" title="' . e($r['error']) . '">ERR</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">Belum ada log.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
