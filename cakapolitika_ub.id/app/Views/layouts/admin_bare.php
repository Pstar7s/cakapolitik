<?php /** @var string $content */ ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Login Admin') ?></title>
<link rel="icon" href="<?= asset('img/logo-cakapolitik.jpeg') ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="cp-login-body">
<?= $content ?>
</body>
</html>
