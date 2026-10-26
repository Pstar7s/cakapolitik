<?php
/** @var array $poll @var int $pollResponden @var array $byKategori @var array $byPersona @var array $recent @var int $total */
$labels = array_keys($byKategori);
$values = array_values($byKategori);

// Siapkan data grafik poll (per pertanyaan).
$pollCharts = [];
foreach ($poll as $q) {
    $pollCharts[] = [
        'id'        => $q['id'],
        'judul'     => $q['judul'],
        'responden' => $q['responden'],
        'labels'    => array_map(fn($o) => $o['label'], $q['opsi']),
        'data'      => array_map(fn($o) => $o['jumlah'], $q['opsi']),
    ];
}
$pollAdaJawaban = array_sum(array_map(fn($c) => array_sum($c['data']), $pollCharts)) > 0;
?>
<section class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
    <div>
      <h1 class="h3 mb-1">Dashboard Aspirasi Warga</h1>
      <p class="text-muted mb-0">
        Dapil Jawa Timur V — Malang Raya ·
        <strong><?= number_format($pollResponden) ?></strong> warga mengisi jajak pendapat ·
        <strong><?= number_format($total) ?></strong> aspirasi teks lolos moderasi
      </p>
    </div>
    <a href="<?= url('/chat') ?>" class="btn btn-cp-gold">Sampaikan aspirasimu</a>
  </div>

  <!-- ===== Hasil Jajak Pendapat (utama) ===== -->
  <h2 class="h5 mb-2">Hasil Jajak Pendapat</h2>
  <?php if (!$pollCharts): ?>
    <div class="cp-card p-3 mb-4"><p class="text-muted mb-0">Belum ada pertanyaan jajak pendapat yang aktif.</p></div>
  <?php elseif (!$pollAdaJawaban): ?>
    <div class="cp-card p-3 mb-4"><p class="text-muted mb-0">Belum ada jawaban warga. Grafik akan muncul setelah warga mulai mengisi jajak pendapat di akhir sesi chat.</p></div>
  <?php else: ?>
    <div class="row g-3 mb-4">
      <?php foreach ($pollCharts as $c): ?>
        <div class="col-lg-6">
          <div class="cp-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
              <h6 class="text-uppercase small mb-0"><?= e($c['judul']) ?></h6>
              <span class="text-muted small"><?= number_format($c['responden']) ?> responden</span>
            </div>
            <div style="height:<?= max(180, 74 * count($c['labels'])) ?>px"><canvas id="pollChart<?= (int)$c['id'] ?>"></canvas></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ===== Aspirasi teks bebas (sekunder) ===== -->
  <h2 class="h5 mb-2">Aspirasi dari Percakapan</h2>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="cp-card p-3 h-100">
        <h6 class="text-muted text-uppercase small mb-3">Aspirasi per kategori</h6>
        <?php if ($total === 0): ?>
          <p class="text-muted mb-0">Belum ada aspirasi teks yang tercatat.</p>
        <?php else: ?>
          <canvas id="kategoriChart" height="230"></canvas>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="cp-card p-3 h-100">
        <h6 class="text-muted text-uppercase small mb-3">Aspirasi per persona</h6>
        <?php if (!$byPersona): ?>
          <p class="text-muted mb-0">Belum ada data.</p>
        <?php else: $mx = max(array_map(fn($r) => (int)$r['c'], $byPersona)) ?: 1; ?>
          <?php foreach ($byPersona as $r): ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span><?= e($r['nama']) ?> <span class="text-muted">(<?= e($r['partai']) ?>)</span></span><strong><?= (int)$r['c'] ?></strong></div>
            <div class="cp-bar" style="width:<?= (int)round($r['c']/$mx*100) ?>%;height:10px"></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="cp-card p-3 mt-3">
    <h6 class="text-muted text-uppercase small mb-3">Aspirasi terbaru</h6>
    <?php if (!$recent): ?>
      <p class="text-muted mb-0">Belum ada aspirasi.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Warga</th><th>Wilayah</th><th>Kepada</th><th>Kategori</th><th>Aspirasi</th><th>Waktu</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= $r['is_anonim'] ? '<em class="text-muted">Anonim</em>' : e($r['warga_nama'] ?: '—') ?></td>
            <td class="small text-muted"><?= e(trim(($r['kecamatan'] ?? '') . ', ' . ($r['kab_kota'] ?? ''), ', ')) ?></td>
            <td class="small"><?= e($r['persona_nama'] ?? '—') ?></td>
            <td><span class="badge bg-secondary text-capitalize"><?= e($r['kategori']) ?></span></td>
            <td class="small"><?= e(mb_strimwidth($r['isi_aspirasi'], 0, 120, '…')) ?></td>
            <td class="small text-muted text-nowrap"><?= e(tgl($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <p class="small text-muted mt-3">
    Jajak pendapat dikumpulkan BRONE di akhir sesi chat. Aspirasi teks yang mengandung indikasi
    ujaran kebencian/SARA/kata kasar/hoaks otomatis ditahan sistem moderasi dan tidak ditampilkan di sini.
  </p>
</section>

<?php if ($pollAdaJawaban || $total > 0): ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
  var NAVY = '#2b3fa0', PALETTE = ['#2b3fa0', '#c9a227', '#3f7fbf', '#5aa469', '#b5546f', '#7a6cc4'];
  if (!window.Chart) return;
  Chart.defaults.font.size = 12;

  // Pecah label panjang jadi beberapa baris agar terbaca di sumbu Y.
  function wrap(s, n) {
    var words = String(s).split(' '), lines = [], cur = '';
    words.forEach(function (w) {
      if ((cur + ' ' + w).trim().length > n) { if (cur) lines.push(cur); cur = w; }
      else { cur = (cur ? cur + ' ' : '') + w; }
    });
    if (cur) lines.push(cur);
    return lines;
  }

  <?php if ($pollAdaJawaban): ?>
  var pollData = <?= json_encode($pollCharts, JSON_UNESCAPED_UNICODE) ?>;
  pollData.forEach(function (q) {
    var el = document.getElementById('pollChart' + q.id);
    if (!el) return;
    var total = q.data.reduce(function (a, b) { return a + b; }, 0) || 1;
    new Chart(el, {
      type: 'bar',
      data: {
        labels: q.labels.map(function (s) { return wrap(s, 34); }),
        datasets: [{
          label: 'Jumlah suara',
          data: q.data,
          backgroundColor: q.labels.map(function (_, i) { return PALETTE[i % PALETTE.length]; }),
          borderRadius: 4,
          maxBarThickness: 26
        }]
      },
      options: {
        indexAxis: 'y',
        maintainAspectRatio: false,
        layout: { padding: { right: 28 } },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) {
            return c.parsed.x + ' suara (' + Math.round(c.parsed.x / total * 100) + '%)';
          } } }
        },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 }, grid: { display: false } },
          y: { ticks: { autoSkip: false, font: { size: 11 } }, grid: { display: false } }
        }
      }
    });
  });
  <?php endif; ?>

  <?php if ($total > 0): ?>
  new Chart(document.getElementById('kategoriChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_map('ucfirst', $labels)) ?>,
      datasets: [{ label: 'Jumlah aspirasi', data: <?= json_encode($values) ?>, backgroundColor: NAVY, borderRadius: 4 }]
    },
    options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
  });
  <?php endif; ?>
})();
</script>
<?php push_script(ob_get_clean()); endif; ?>
