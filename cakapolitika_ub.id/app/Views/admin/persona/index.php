<?php /** @var array $personas @var ?string $success */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Persona &amp; Knowledge Base</h1>
  <a href="<?= url('/admin/persona/create') ?>" class="btn btn-cp-navy">+ Persona baru</a>
</div>
<?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>

<div class="cp-stat">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Nama</th><th>Partai / Jabatan</th><th class="text-center">KB</th><th class="text-center">Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($personas as $p): ?>
        <tr>
          <td class="fw-semibold"><?= e($p['nama']) ?></td>
          <td class="small"><?= e($p['partai']) ?><br><span class="text-muted"><?= e($p['jabatan']) ?></span></td>
          <td class="text-center"><?= (int)$p['chunk_count'] ?></td>
          <td class="text-center"><?= (int)$p['status_aktif'] === 1 ? '<span class="badge bg-success">aktif</span>' : '<span class="badge bg-secondary">nonaktif</span>' ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/persona/' . $p['id'] . '/knowledge') ?>">Knowledge</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/persona/' . $p['id'] . '/edit') ?>">Edit</a>
            <form method="post" action="<?= url('/admin/persona/' . $p['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Hapus persona ini beserta knowledge base-nya?')">
              <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
