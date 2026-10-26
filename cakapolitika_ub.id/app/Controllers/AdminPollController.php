<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aspirasi;
use App\Models\Poll;
use App\Models\PollOpsi;

/**
 * Panel admin untuk menyusun jajak pendapat aspirasi (poll) yang disajikan BRONE.
 * Pola mengikuti AdminPersonaController + view knowledge base.
 */
class AdminPollController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();
        return $this->render('admin/polling/index', [
            'title'      => 'Polling Aspirasi — Admin',
            'pertanyaan' => Poll::allForAdmin(),
            'kategori'   => Aspirasi::KATEGORI,
            'responden'  => Poll::totalResponden(),
            'success'    => flash('success'),
        ], 'layouts/admin');
    }

    public function edit(string $id): string
    {
        $this->requireAdmin();
        $q = Poll::find((int)$id);
        if (!$q) {
            return $this->redirect('/admin/polling');
        }
        return $this->render('admin/polling/form', [
            'title'    => 'Edit Pertanyaan Poll — Admin',
            'q'        => $q,
            'opsi'     => Poll::optionsFor((int)$id),
            'kategori' => Aspirasi::KATEGORI,
            'jawaban'  => Poll::countJawaban((int)$id),
            'success'  => flash('success'),
        ], 'layouts/admin');
    }

    public function store(): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $id = Poll::create($this->payload());
        flash('success', 'Pertanyaan ditambahkan. Sekarang isi opsi jawabannya.');
        return $this->redirect('/admin/polling/' . $id);
    }

    public function update(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        if (!Poll::find((int)$id)) {
            return $this->redirect('/admin/polling');
        }
        Poll::updateById((int)$id, $this->payload());
        flash('success', 'Pertanyaan diperbarui.');
        return $this->redirect('/admin/polling/' . $id);
    }

    public function destroy(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        Poll::deleteById((int)$id); // opsi ikut terhapus (ON DELETE CASCADE)
        flash('success', 'Pertanyaan dihapus.');
        return $this->redirect('/admin/polling');
    }

    // --- Opsi jawaban --------------------------------------------------

    public function optionStore(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $q = Poll::find((int)$id);
        if (!$q) {
            return $this->redirect('/admin/polling');
        }
        $label = trim($_POST['label'] ?? '');
        if ($label !== '') {
            PollOpsi::create([
                'pertanyaan_id' => (int)$id,
                'label'         => $label,
                'urutan'        => (int)($_POST['urutan'] ?? 0),
                'is_aktif'      => 1,
            ]);
            flash('success', 'Opsi jawaban ditambahkan.');
        }
        return $this->redirect('/admin/polling/' . $id);
    }

    public function optionUpdate(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $opsi = PollOpsi::find((int)$id);
        if (!$opsi) {
            return $this->redirect('/admin/polling');
        }
        if (isset($_POST['delete'])) {
            PollOpsi::deleteById((int)$id);
            flash('success', 'Opsi dihapus.');
        } else {
            PollOpsi::updateById((int)$id, [
                'label'    => trim($_POST['label'] ?? ''),
                'urutan'   => (int)($_POST['urutan'] ?? 0),
                'is_aktif' => isset($_POST['is_aktif']) ? 1 : 0,
            ]);
            flash('success', 'Opsi diperbarui.');
        }
        return $this->redirect('/admin/polling/' . $opsi['pertanyaan_id']);
    }

    private function payload(): array
    {
        $tipe = ($_POST['tipe'] ?? 'ganda') === 'checklist' ? 'checklist' : 'ganda';
        $max  = max(1, (int)($_POST['max_pilihan'] ?? 1));
        return [
            'judul'       => trim($_POST['judul'] ?? ''),
            'deskripsi'   => trim($_POST['deskripsi'] ?? ''),
            'kategori'    => in_array($_POST['kategori'] ?? '', Aspirasi::KATEGORI, true) ? $_POST['kategori'] : null,
            'tipe'        => $tipe,
            'max_pilihan' => $tipe === 'ganda' ? 1 : $max,
            'urutan'      => (int)($_POST['urutan'] ?? 0),
            'is_aktif'    => isset($_POST['is_aktif']) ? 1 : 0,
        ];
    }
}
