<?php /** @var array $admin @var ?string $success @var ?string $error */ ?>
<h1 class="h4 mb-3">Akun Admin</h1>
<?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

<div class="cp-stat" style="max-width:460px">
  <p class="mb-1"><strong><?= e($admin['username']) ?></strong> <span class="badge bg-secondary"><?= e($admin['role']) ?></span></p>
  <p class="small text-muted">Login terakhir: <?= e($admin['last_login_at'] ? tgl($admin['last_login_at']) : '—') ?></p>
  <hr>
  <form method="post" action="<?= url('/admin/akun') ?>">
    <?= csrf_field() ?>
    <div class="mb-2"><label class="form-label small">Password saat ini</label><input type="password" name="current_password" class="form-control" required></div>
    <div class="mb-2"><label class="form-label small">Password baru (min. 8 karakter)</label><input type="password" name="new_password" class="form-control" required></div>
    <div class="mb-3"><label class="form-label small">Ulangi password baru</label><input type="password" name="confirm_password" class="form-control" required></div>
    <button class="btn btn-cp-navy">Perbarui password</button>
  </form>
</div>
