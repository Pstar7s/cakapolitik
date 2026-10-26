<?php
/** @var string $content */
$me = $u ?? null;
$_menu = [];
if ($me) {
    if ($me['role'] === 'admin') {
        $_menu = ['/admin' => 'Ringkasan', '/admin/kelas' => 'Kelas', '/admin/kasus' => 'Kasus', '/admin/pengguna' => 'Pengguna', '/dosen' => 'Sesi'];
    } elseif ($me['role'] === 'dosen') {
        $_menu = ['/dosen' => 'Sesi & Kelas'];
    } else {
        $_menu = ['/mhs' => 'Sesi Saya'];
    }
}
$_flash = flash();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'CAKAPROOM') ?></title>
<meta name="robots" content="noindex">
<link rel="icon" href="<?= asset('img/logo-cakapolitik.jpeg') ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="cr-nav navbar navbar-expand-md">
  <div class="container">
    <a class="cr-brand navbar-brand" href="<?= url('/') ?>">CAKA<span>PROOM</span></a>
    <?php if ($me): ?>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nv"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav me-auto">
        <?php foreach ($_menu as $href => $lbl): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url($href) ?>"><?= e($lbl) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <form method="post" action="<?= url('/logout') ?>" class="d-flex align-items-center gap-2">
        <?= csrf_field() ?>
        <span class="small mut"><?= e($me['nama']) ?> · <?= e($me['role']) ?></span>
        <button class="btn btn-sm btn-outline-secondary">Keluar</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</nav>

<main class="container container-narrow py-4">
  <?php if (!empty($_flash['ok'])): ?><div class="alert alert-success"><?= e($_flash['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_flash['error'])): ?><div class="alert alert-danger"><?= e($_flash['error']) ?></div><?php endif; ?>
  <?= $content ?>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<?= page_scripts() ?>
</body>
</html>
