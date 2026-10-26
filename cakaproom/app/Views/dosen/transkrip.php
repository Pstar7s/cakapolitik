<a href="<?= url('/dosen/sesi/' . (int)$sesi['id']) ?>" class="small">&larr; Kontrol sesi</a>
<h1 class="h4 mb-1">Transkrip — <?= e($sesi['judul'] ?: $kasus['judul']) ?></h1>
<p class="mut small">Hanya untuk dosen. Nama asli ditampilkan; data ke layanan AI selalu dianonimkan.</p>

<?php
$grup = ['argumen' => 'Fase 1 · Argumen', 'pertanyaan' => 'Fase 2 · Pertanyaan uji silang', 'jawaban' => 'Fase 2 · Jawaban', 'negosiasi' => 'Fase 3 · Panel negosiasi'];
foreach ($grup as $tipe => $judul):
  $items = array_filter($posts, fn($p) => $p['tipe'] === $tipe);
?>
<h2 class="h6 mt-4"><?= e($judul) ?> <span class="mut">(<?= count($items) ?>)</span></h2>
<?php foreach ($items as $p): ?>
  <div class="feed-item bl-<?= e($p['peran']) ?>">
    <div class="meta"><span class="badge-peran bg-<?= e($p['peran']) ?>"><?= e(peran_label($p['peran'])) ?></span><strong><?= e($p['nama']) ?></strong>
      <span>#<?= (int)$p['id'] ?></span><?= $p['parent_id'] ? '<span>&rarr; #' . (int)$p['parent_id'] . '</span>' : '' ?><?= $p['target'] && $tipe === 'pertanyaan' ? '<span>ke ' . e(peran_label($p['target'])) . '</span>' : '' ?>
      <span><?= (int)$p['kata'] ?> kata</span><span><?= e($p['waktu']) ?></span></div>
    <?php if ($tipe === 'argumen'): ?>
      <div class="lbl small mut fw-bold">CLAIM</div><div><?= teks($p['claim']) ?></div>
      <div class="lbl small mut fw-bold mt-2">EVIDENCE</div><div><?= teks($p['evidence']) ?></div>
      <div class="lbl small mut fw-bold mt-2">NATIONAL VALUE ALIGNMENT</div><div><?= teks($p['nilai']) ?></div>
    <?php else: ?><div><?= teks($p['isi']) ?></div><?php endif; ?>
  </div>
<?php endforeach; endforeach; ?>

<h2 class="h6 mt-4">Joint Resolution</h2>
<?php foreach ($klausul as $k): ?>
  <div class="klausul"><strong><?= (int)$k['no'] ?>. <?= e($k['judul']) ?></strong>
    <div class="isi mt-1"><?= trim((string)$k['teks']) !== '' ? teks($k['teks']) : '<span class="mut">(belum diisi)</span>' ?></div>
    <?php if ($k['editor']): ?><div class="small mut mt-1">Penyunting terakhir: <?= e($k['editor']) ?></div><?php endif; ?></div>
<?php endforeach; ?>
<div class="card-cr p-3 mb-3"><strong>Sikap peran</strong>
<?php foreach (array_keys(PERAN) as $kode): $s = $sikap[$kode] ?? null; ?>
  <div class="small mt-1"><span class="badge-peran bg-<?= e($kode) ?>"><?= e(peran_label($kode)) ?></span> <?= $s ? '<strong>' . e($s['sikap']) . '</strong>' . ($s['catatan'] ? ' — ' . e($s['catatan']) : '') . ' <span class="mut">(' . e($s['oleh']) . ')</span>' : '<span class="mut">belum mencatat</span>' ?></div>
<?php endforeach; ?></div>
<?php if ($resolusi): ?><details class="mb-4"><summary class="small mut">Snapshot teks final tersimpan</summary><pre class="plain small mt-2"><?= e($resolusi) ?></pre></details><?php endif; ?>
