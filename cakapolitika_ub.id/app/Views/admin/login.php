<?php /** @var ?string $error */ ?>
<div class="cp-login-card">
  <div class="text-center mb-3">
    <img src="<?= asset('img/logo-cakapolitik.jpeg') ?>" alt="" height="40">
    <div class="text-muted small mt-2">Panel Admin</div>
  </div>
  <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= url('/admin/login') ?>">
    <?= csrf_field() ?>
    <div class="mb-2">
      <label class="form-label small">Username</label>
      <input name="username" class="form-control" autofocus required>
    </div>
    <div class="mb-3">
      <label class="form-label small">Password</label>
      <input name="password" type="password" class="form-control" required>
    </div>
    <button class="btn btn-cp-navy w-100">Masuk</button>
  </form>
  <div class="text-center mt-3"><a href="<?= url('/') ?>" class="small text-muted">← ke situs</a></div>
</div>
