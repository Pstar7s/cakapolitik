<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\SesiService;

/** Basis controller: pemuatan sesi + pemeriksaan kepemilikan/keanggotaan. */
abstract class SesiAccess extends Controller
{
    /** Sesi + kelas + kasus; 404 JSON/HTML bila tidak ada. */
    protected function sesiOr404(int $id): array
    {
        $s = SesiService::find($id);
        if (!$s) {
            if ($this->wantsJson()) { $this->fail('Sesi tidak ditemukan.', 404); }
            else { http_response_code(404); $this->view->render('errors/404', [], 'layouts/app'); }
            exit;
        }
        return $s;
    }

    /** Dosen pemilik kelas (atau admin). */
    protected function dosenSesi(int $id): array
    {
        $u = $this->requireRole('dosen', 'admin');
        $s = $this->sesiOr404($id);
        if ($u['role'] !== 'admin') {
            $dosenId = Database::value('SELECT dosen_id FROM cr_kelas WHERE id = ?', [$s['kelas_id']]);
            if ((int)$dosenId !== (int)$u['id']) {
                if ($this->wantsJson()) { $this->fail('Sesi ini bukan milik kelas Anda.', 403); }
                else { http_response_code(403); $this->view->render('errors/403', [], 'layouts/app'); }
                exit;
            }
        }
        return [$u, $s];
    }

    /** Mahasiswa yang terdaftar di kelas sesi ini. */
    protected function mhsSesi(int $id): array
    {
        $u = $this->requireRole('mahasiswa');
        $s = $this->sesiOr404($id);
        if (!SesiService::dalamKelas((int)$s['kelas_id'], (int)$u['id'])) {
            if ($this->wantsJson()) { $this->fail('Anda tidak terdaftar di kelas ini.', 403); }
            else { http_response_code(403); $this->view->render('errors/403', [], 'layouts/app'); }
            exit;
        }
        return [$u, $s];
    }

    protected function kasus(int $id): array
    {
        return Database::one('SELECT * FROM cr_kasus WHERE id = ?', [$id]) ?? [];
    }
}
