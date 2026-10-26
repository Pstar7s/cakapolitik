<?php
/** @var array|null $dapil @var array $personas @var array $summary */
$totalAsp = array_sum($summary);
?>
<section class="cp-hero">
  <div class="container py-5">
    <div class="row align-items-center gy-4">
      <div class="col-lg-7">
        <span class="cp-chip mb-3">Purwarupa Fase 1 · Dapil Jawa Timur V — Malang Raya</span>
        <h1 class="display-5 mb-3">Sampaikan aspirasimu langsung ke pemimpin politik daerahmu.</h1>
        <p class="lead text-white-50 mb-4">
          CAKAPOLITIK_UB.ID adalah ruang publik digital berbasis AI. Ditemani host <strong class="text-white">BRONE</strong>,
          kamu bisa berdialog dengan persona AI dari anggota DPR RI di dapilmu — dan setiap aspirasimu tercatat rapi.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= url('/chat') ?>" class="btn btn-cp-gold btn-lg">Mulai Ngobrol dengan BRONE</a>
          <a href="<?= url('/dashboard') ?>" class="btn btn-outline-light btn-lg">Lihat Dashboard Aspirasi</a>
        </div>
      </div>
      <div class="col-lg-5 text-center">
        <img src="<?= asset('img/brone-maskot.png') ?>" alt="BRONE, maskot CAKAPOLITIK_UB.ID" class="cp-maskot">
      </div>
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="row g-4">
    <div class="col-md-4">
      <div class="d-flex gap-3">
        <div class="cp-step-num">1</div>
        <div><h5 class="mb-1">Verifikasi singkat</h5><p class="text-muted mb-0">Isi nama &amp; alamat, lalu masukkan kode OTP yang dikirim ke emailmu. Tanpa NIK.</p></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="d-flex gap-3">
        <div class="cp-step-num">2</div>
        <div><h5 class="mb-1">Pilih pemimpin politik</h5><p class="text-muted mb-0">BRONE menampilkan tokoh dari dapilmu yang sudah bergabung di program ini.</p></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="d-flex gap-3">
        <div class="cp-step-num">3</div>
        <div><h5 class="mb-1">Ngobrol &amp; beraspirasi</h5><p class="text-muted mb-0">Tanya atau sampaikan aspirasi. Persona AI menjawab berdasar data publik; aspirasimu otomatis dikategorikan.</p></div>
      </div>
    </div>
  </div>
</section>

<section class="container pb-5">
  <div class="cp-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h4 class="mb-0">Persona tersedia
        <?php if ($dapil): ?><small class="text-muted fs-6">· Dapil <?= e($dapil['nama_dapil']) ?></small><?php endif; ?>
      </h4>
      <a href="<?= url('/chat') ?>" class="btn btn-sm btn-cp-navy">Ajak bicara</a>
    </div>
    <div class="row g-3">
      <?php foreach ($personas as $p): ?>
      <div class="col-md-6">
        <div class="d-flex gap-3 p-3 border rounded-3 h-100">
          <?php $photo = $p['foto_url'] ? (str_starts_with($p['foto_url'],'http') ? $p['foto_url'] : asset(preg_replace('#^assets/#','',$p['foto_url']))) : null; ?>
          <img class="cp-persona-photo" src="<?= e($photo ?: asset('img/brone-maskot.png')) ?>" alt="<?= e($p['nama']) ?>">
          <div>
            <div class="fw-bold"><?= e($p['nama']) ?></div>
            <div class="small text-muted mb-1"><?= e($p['partai']) ?> · <?= e($p['jabatan']) ?></div>
            <div class="small"><?= e(mb_strimwidth($p['deskripsi_singkat'], 0, 150, '…')) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$personas): ?><div class="col-12 text-muted">Belum ada persona aktif.</div><?php endif; ?>
    </div>
    <p class="small text-muted mt-3 mb-0">
      Pemilihan nama tokoh sebagai sampel purwarupa belum berarti mendapat persetujuan yang bersangkutan.
      Jawaban persona dihasilkan AI berdasarkan informasi publik.
    </p>
  </div>
</section>

<?php if ($totalAsp > 0): ?>
<section class="container pb-5">
  <div class="cp-card p-4">
    <h5 class="mb-3"><?= number_format($totalAsp) ?> aspirasi warga tercatat</h5>
    <?php $max = max($summary) ?: 1; foreach ($summary as $k => $v): if ($v === 0) continue; ?>
    <div class="cp-bar-row">
      <div class="small text-capitalize"><?= e($k) ?></div>
      <div class="cp-bar" style="width:<?= (int)round($v / $max * 100) ?>%"></div>
      <div class="small text-end"><?= $v ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
