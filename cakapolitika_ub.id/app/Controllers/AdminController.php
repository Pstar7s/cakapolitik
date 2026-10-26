<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\AdminUser;
use App\Models\ApiLog;
use App\Models\Aspirasi;
use App\Models\KnowledgeChunk;
use App\Models\Persona;
use App\Services\ClaudeClient;

class AdminController extends Controller
{
    public function dashboard(): string
    {
        $admin = $this->requireAdmin();

        $personas = Persona::all('nama ASC');
        foreach ($personas as &$p) {
            $p['chunk_count'] = KnowledgeChunk::countForPersona((int)$p['id']);
        }
        unset($p);

        return $this->render('admin/dashboard', [
            'title'    => 'Panel Admin — CAKAPOLITIK_UB.ID',
            'admin'    => $admin,
            'stats'    => [
                'warga'          => (int)Database::value('SELECT COUNT(*) FROM warga'),
                'warga_verified' => (int)Database::value('SELECT COUNT(*) FROM warga WHERE is_verified = 1'),
                'aspirasi'       => (int)Database::value('SELECT COUNT(*) FROM aspirasi'),
                'aspirasi_lolos' => (int)Database::value("SELECT COUNT(*) FROM aspirasi WHERE status_moderasi = 'lolos'"),
                'aspirasi_tahan' => (int)Database::value("SELECT COUNT(*) FROM aspirasi WHERE status_moderasi = 'ditahan'"),
                'percakapan'     => (int)Database::value('SELECT COUNT(*) FROM percakapan'),
            ],
            'personas'   => $personas,
            'byKategori' => Aspirasi::summaryByKategori(),
            'apiStats'   => ApiLog::stats(),
            'stubMode'   => (new ClaudeClient())->stub,
        ], 'layouts/admin');
    }

    public function apiLog(): string
    {
        $this->requireAdmin();
        $rows = Database::all('SELECT * FROM api_log ORDER BY id DESC LIMIT 200');
        return $this->render('admin/api_log', [
            'title' => 'Log API Claude — Admin',
            'rows'  => $rows,
            'stats' => ApiLog::stats(),
        ], 'layouts/admin');
    }

    public function account(): string
    {
        $admin = $this->requireAdmin();
        return $this->render('admin/account', [
            'title'   => 'Akun Admin',
            'admin'   => $admin,
            'success' => flash('success'),
            'error'   => flash('error'),
        ], 'layouts/admin');
    }

    public function updateAccount(): string
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf();

        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $admin['password_hash'])) {
            flash('error', 'Password saat ini salah.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } elseif ($new !== $confirm) {
            flash('error', 'Konfirmasi password tidak cocok.');
        } else {
            AdminUser::setPassword((int)$admin['id'], $new);
            flash('success', 'Password berhasil diperbarui.');
        }
        return $this->redirect('/admin/akun');
    }
}
