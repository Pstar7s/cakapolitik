<?php
namespace App\Controllers;

use App\Core\Database;
use App\Services\PostService;
use App\Services\ResolusiService;
use App\Services\SesiService;
use App\Services\StateService;

class MahasiswaController extends SesiAccess
{
    /** Daftar sesi untuk kelas-kelas mahasiswa. */
    public function index(): string
    {
        $u = $this->requireRole('mahasiswa');
        $sesi = Database::all(
            "SELECT s.id, s.judul, s.status, s.fase_aktif, s.created_at, s.nilai_publik, k.nama_kelas, ks.judul AS kasus
               FROM cr_sesi s
               JOIN cr_kelas k ON k.id = s.kelas_id
               JOIN cr_kelas_mahasiswa km ON km.kelas_id = s.kelas_id AND km.user_id = ?
               JOIN cr_kasus ks ON ks.id = s.kasus_id
              ORDER BY FIELD(s.status,'berjalan','lobby','selesai','dinilai','draft'), s.id DESC LIMIT 30", [$u['id']]);
        return $this->render('mhs/index', ['title' => 'Sesi Saya — CAKAPROOM', 'u' => $u, 'sesi' => $sesi]);
    }

    /** Ruang sesi. Halaman memuat state awal lalu polling. */
    public function room(int|string $id): string
    {
        [$u, $s] = $this->mhsSesi((int)$id);
        $peserta = SesiService::peserta((int)$s['id'], (int)$u['id']);
        $kasus = $this->kasus((int)$s['kasus_id']);
        $peran = Database::all('SELECT * FROM cr_kasus_peran WHERE kasus_id = ?', [$kasus['id']]);
        $klausul = Database::all('SELECT * FROM cr_kasus_klausul WHERE kasus_id = ? ORDER BY no', [$kasus['id']]);
        return $this->render('mhs/room', [
            'title' => ($s['judul'] ?: $kasus['judul']) . ' — CAKAPROOM',
            'u' => $u, 'sesi' => $s, 'kasus' => $kasus, 'briefs' => array_column($peran, 'brief_peran', 'kode_peran'),
            'klausul' => $klausul, 'peserta' => $peserta,
            'cfg' => [
                'sesiId' => (int)$s['id'], 'userId' => (int)$u['id'],
                'api' => url('/api/sesi/' . (int)$s['id']),
                'pollMs' => (int)config('sesi.poll_ms'),
                'pertanyaanMax' => (int)config('sesi.pertanyaan_max_kata'),
                'jawabanMax' => (int)config('sesi.jawaban_max_kata'),
                'negMax' => (int)config('sesi.negosiasi_max_char'),
                'maxArgumen' => (int)config('sesi.max_argumen_per_mhs'),
                'peran' => PERAN,
            ],
        ], 'layouts/room');
    }

    public function join(int|string $id): string
    {
        [$u, $s] = $this->mhsSesi((int)$id);
        $this->verifyCsrf();
        try {
            $p = SesiService::join($s, (int)$u['id']);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        }
        return $this->ok(['peran' => $p['kode_peran'], 'delegasi' => (bool)$p['is_delegasi']]);
    }

    /** Sumber kebenaran untuk klien (polling / reconnect): GET /state?since=&rev=&res_rev= */
    public function state(int|string $id): string
    {
        [$u, $s] = $this->mhsSesi((int)$id);
        session_write_close();   // jangan menahan kunci sesi PHP selama polling
        $peserta = SesiService::peserta((int)$s['id'], (int)$u['id']);
        if (!$peserta) {
            return $this->fail('Anda belum masuk ke sesi ini.', 409, ['perlu_join' => true]);
        }
        Database::run('UPDATE cr_peserta_sesi SET last_seen = NOW() WHERE sesi_id = ? AND user_id = ?', [$s['id'], $u['id']]);
        $st = StateService::build($s, $peserta, max(0, (int)($_GET['since'] ?? 0)), (int)($_GET['rev'] ?? 0), (int)($_GET['res_rev'] ?? -1), false);
        return $this->json($st);
    }

    public function post(int|string $id): string
    {
        [$u, $s] = $this->mhsSesi((int)$id);
        $this->verifyCsrf();
        session_write_close();
        $peserta = SesiService::peserta((int)$s['id'], (int)$u['id']);
        if (!$peserta) return $this->fail('Anda belum masuk ke sesi ini.', 409);
        $r = PostService::submit($s, $peserta, $this->body());
        if (!$r['ok']) {
            $extra = isset($r['jumlah_kata']) ? ['jumlah_kata' => $r['jumlah_kata']] : [];
            return $this->fail($r['error'], 422, $extra);
        }
        return $this->ok(['id' => (int)$r['post']['id'], 'duplikat' => !empty($r['duplikat'])]);
    }

    // --- Joint Resolution ---------------------------------------------------------------

    private function delegasiCtx(int $id): array
    {
        [$u, $s] = $this->mhsSesi($id);
        $this->verifyCsrf();
        session_write_close();
        $peserta = SesiService::peserta((int)$s['id'], (int)$u['id']);
        if (!$peserta) { $this->fail('Anda belum masuk ke sesi ini.', 409); exit; }
        return [$s, $peserta];
    }

    public function kunci(int|string $id): string
    {
        [$s, $p] = $this->delegasiCtx((int)$id);
        $r = ResolusiService::kunci($s, $p, $this->int('no'));
        return $r['ok'] ? $this->ok($r) : $this->fail($r['error'], !empty($r['terkunci']) ? 409 : 403, ['terkunci' => !empty($r['terkunci'])]);
    }

    public function simpan(int|string $id): string
    {
        [$s, $p] = $this->delegasiCtx((int)$id);
        $r = ResolusiService::simpan($s, $p, $this->int('no'), (string)$this->input('teks', ''), (bool)$this->input('lepas', false));
        return $r['ok'] ? $this->ok($r) : $this->fail($r['error'], !empty($r['terkunci']) ? 409 : 403, ['terkunci' => !empty($r['terkunci'])]);
    }

    public function sikap(int|string $id): string
    {
        [$s, $p] = $this->delegasiCtx((int)$id);
        $r = ResolusiService::setSikap($s, $p, $this->str('sikap'), $this->str('catatan'));
        return $r['ok'] ? $this->ok() : $this->fail($r['error'], 403);
    }
}
