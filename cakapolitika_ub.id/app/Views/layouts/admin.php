<?php /** @var string $content */ ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Admin — CAKAPOLITIK_UB.ID') ?></title>
<link rel="icon" href="<?= asset('img/logo-cakapolitik.jpeg') ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="cp-admin-body">
<div class="cp-admin-wrap">
  <aside class="cp-admin-side">
    <div class="cp-admin-brand">
      <img src="<?= asset('img/logo-cakapolitik.jpeg') ?>" alt="" height="26">
      <span>Admin</span>
    </div>
    <?php $u = $_SERVER['REQUEST_URI'] ?? ''; ?>
    <nav class="nav flex-column">
      <a class="nav-link <?= str_contains($u, '/admin') && !str_contains($u,'/admin/') ? 'active' : '' ?>" href="<?= url('/admin') ?>">Ringkasan</a>
      <a class="nav-link <?= str_contains($u, '/admin/persona') ? 'active' : '' ?>" href="<?= url('/admin/persona') ?>">Persona &amp; Knowledge</a>
      <a class="nav-link <?= str_contains($u, '/admin/aspirasi') ? 'active' : '' ?>" href="<?= url('/admin/aspirasi') ?>">Aspirasi Masuk</a>
      <a class="nav-link <?= str_contains($u, '/admin/polling') ? 'active' : '' ?>" href="<?= url('/admin/polling') ?>">Polling Aspirasi</a>
      <a class="nav-link <?= str_contains($u, '/admin/api-log') ? 'active' : '' ?>" href="<?= url('/admin/api-log') ?>">Log &amp; Biaya API</a>
      <a class="nav-link <?= str_contains($u, '/admin/akun') ? 'active' : '' ?>" href="<?= url('/admin/akun') ?>">Akun</a>
    </nav>
    <form method="post" action="<?= url('/admin/logout') ?>" class="mt-auto p-3">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-outline-light w-100">Keluar</button>
    </form>
  </aside>
  <div class="cp-admin-main">
    <div class="container-fluid p-4"><?= $content ?></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<?= page_scripts() ?>
</body>
</html>
