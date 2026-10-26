<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aspirasi;
use App\Models\Persona;

class AdminAspirasiController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();
        $filters = [
            'kategori'   => $_GET['kategori'] ?? '',
            'status'     => $_GET['status'] ?? '',
            'persona_id' => $_GET['persona_id'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $result = Aspirasi::paginate($filters, $page, 20);

        return $this->render('admin/aspirasi/index', [
            'title'    => 'Aspirasi Masuk — Admin',
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => 20,
            'filters'  => $filters,
            'personas' => Persona::all('nama ASC'),
            'kategoris'=> Aspirasi::KATEGORI,
            'success'  => flash('success'),
        ], 'layouts/admin');
    }

    public function moderate(string $id): string
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['lolos', 'ditahan', 'belum_dicek'], true)) {
            Aspirasi::updateById((int)$id, [
                'status_moderasi' => $status,
                'alasan_moderasi' => trim($_POST['alasan'] ?? '') ?: 'Diperbarui manual oleh admin.',
            ]);
            flash('success', 'Status moderasi aspirasi #' . (int)$id . ' diperbarui.');
        }
        return $this->redirect('/admin/aspirasi?' . http_build_query(array_filter([
            'kategori'   => $_POST['back_kategori'] ?? '',
            'status'     => $_POST['back_status'] ?? '',
            'persona_id' => $_POST['back_persona'] ?? '',
            'page'       => $_POST['back_page'] ?? 1,
        ])));
    }
}
