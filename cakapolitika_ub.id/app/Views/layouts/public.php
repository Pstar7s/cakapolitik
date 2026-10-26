<?php /** @var string $content */ ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'CAKAPOLITIK_UB.ID') ?></title>
<meta name="description" content="Ruang publik digital yang mempertemukan aspirasi warga dengan pemimpin politik di Malang Raya.">
<link rel="icon" href="<?= asset('img/logo-cakapolitik.jpeg') ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="cp-body">
<nav class="navbar navbar-expand-lg cp-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('/') ?>">
      <img src="<?= asset('img/logo-cakapolitik.jpeg') ?>" alt="CAKAPOLITIK_UB.ID" height="34">
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="nav-link" href="<?= url('/') ?>">Beranda</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('/dashboard') ?>">Dashboard Aspirasi</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('/tentang') ?>">Tentang</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-cp-gold" href="<?= url('/chat') ?>">Mulai Ngobrol</a></li>
      </ul>
    </div>
  </div>
</nav>

<main><?= $content ?></main>

<footer class="cp-footer mt-5">
  <div class="container py-4">
    <div class="row gy-3 align-items-center">
      <div class="col-md-7">
        <strong>CAKAPOLITIK_UB.ID</strong> — Memperkuat Demokrasi di Ruang Digital<br>
        <small class="text-white-50">Purwarupa Fase 1 (MVP) · Dapil Jawa Timur V — Malang Raya. Persona menjawab dengan AI berdasarkan data publik dan belum tentu mencerminkan pernyataan resmi tokoh terkait.</small>
      </div>
      <div class="col-md-5 text-md-end">
        <span class="text-white-50 small d-block mb-1">Powered by</span>
        <img src="<?= asset('img/logo-globalizing-ub.png') ?>" alt="Globalizing UB" height="34" class="bg-white rounded p-1 me-2">
        <img src="<?= asset('img/logo-indopol.png') ?>" alt="Indopol Survey" height="30" class="bg-white rounded p-1">
      </div>
    </div>
  </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<?= page_scripts() ?>
</body>
</html>
