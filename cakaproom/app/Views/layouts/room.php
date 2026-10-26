<?php /** @var string $content */ ?>
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
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<?= $content ?>
<div class="toastbox" id="toasts" aria-live="polite"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/common.js') ?>"></script>
<?= page_scripts() ?>
</body>
</html>
