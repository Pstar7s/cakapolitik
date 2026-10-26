<?php
namespace App\Controllers;

use App\Core\Database;
use App\Services\NilaiService;
use App\Services\ResolusiService;
use App\Services\ScoringService;
use App\Services\SesiService;
use App\Services\StateService;

class DosenController extends SesiAccess
{
    private function kelasMilik(array $u): array
    {
        return $u['role'] === 'admin'
            ? Database::all('SELECT k.*, (SELECT COUNT(*) FROM cr_kelas_mahasiswa WHERE kelas_id = k.id) AS jml FROM cr_kelas k ORDER BY k.id DESC')
            : Database::all('SELECT k.*, (SELECT COUNT(*) FROM cr_kelas_mahasiswa WHERE kelas_id = k.id) AS jml FROM cr_kelas k WHERE dosen_id = ? ORDER BY k.id DESC', [$u['id']]);
    }

    public function index(): string
    {
        $u = $this->requireRole('dosen', 'admin');
        $kelas = $this->kelasMilik($u);
        $ids = array_column($kelas, 'id');
        $sesi = [];
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            $sesi = Database::all(
                "SELECT s.*, k.nama_kelas, ks.judul AS kasus FROM cr_sesi s JOIN cr_kelas k ON k.id = s.kelas_id
                   JOIN cr_kasus ks ON ks.id = s.kasus_id WHERE s.kelas_id IN ($in) ORDER BY s.id DESC LIMIT 50");
        }
        return $this->render('dosen/index', ['title' => 'Dosen — CAKAPROOM', 'u' => $u, 'kelas' => $kelas, 'sesi' => $sesi]);
    }

    public function formSesi(): string
    {
        $u = $this->requireRole('dosen', 'admin');
        return $this->render('dosen/sesi_baru', [
            'title' => 'Sesi Baru — CAKAPROOM', 'u' => $u, 'kelas' => $this->kelasMilik($u),
            'kasus' => Database::all("SELECT id, judul, status FROM cr_kasus ORDER BY id DESC"),
            'durasi' => config('sesi.durasi'), 'wmin' => config('sesi.word_min'), 'wmax' => config('sesi.word_max'),
        ]);
    }

    public function buatSesi(): string
    {
        $u = $this->requireRole('dosen', 'admin');
        $this->verifyCsrf();
        $kelas = $this->int('kelas_id'); $kasus = $this->int('kasus_id');
        $milik = array_column($this->kelasMilik($u), 'id');
        if (!in_array($kelas, array_map('intval', $milik), true)) {
            flash('error', 'Kelas tidak valid.');
            return $this->redirect('/dosen/sesi/baru');
        }
        $k = $this->kasus($kasus);
        if (!$k || $k['status'] !== 'siap') {
            flash('error', 'Pilih kasus dengan status "siap".');
            return $this->redirect('/dosen/sesi/baru');
        }
        $menit = [];
        foreach (array_keys(config('sesi.durasi')) as $f) {
            $v = $_POST['menit_' . $f] ?? null;
            if (is_numeric($v)) $menit[$f] = (float)$v;
        }
        $id = SesiService::create($kelas, $kasus, $this->str('judul') ?: $k['judul'], (int)$u['id'], [
            'durasi_menit' => $menit, 'word_min' => $this->int('word_min', 100), 'word_max' => $this->int('word_max', 200),
        ]);
        $this->audit('sesi_dibuat', ['sesi' => $id]);
        return $this->redirect('/dosen/sesi/' . $id);
    }

    /** Dashboard dosen (live + pasca-sesi). */
    public function sesi(int|string $id): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $kasus = $this->kasus((int)$s['kasus_id']);
        $kelas = Database::one('SELECT * FROM cr_kelas WHERE id = ?', [$s['kelas_id']]);
        return $this->render('dosen/sesi', [
            'title' => 'Kontrol Sesi — CAKAPROOM', 'u' => $u, 'sesi' => $s, 'kasus' => $kasus, 'kelas' => $kelas,
            'durasi' => SesiService::durasi($s),
            'cfg' => [
                'sesiId' => (int)$s['id'], 'api' => url('/api/dosen/sesi/' . (int)$s['id']),
                'stateApi' => url('/api/dosen/sesi/' . (int)$s['id'] . '/state'),
                'pollMs' => (int)config('sesi.poll_ms'), 'peran' => PERAN,
                'batas' => config('nilai.batas_individu'),
            ],
        ], 'layouts/room');
    }

    public function state(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        session_write_close();
        $st = StateService::build($s, null, max(0, (int)($_GET['since'] ?? 0)), (int)($_GET['rev'] ?? 0), (int)($_GET['res_rev'] ?? -1), true);
        return $this->json($st);
    }

    // --- Kontrol ---------------------------------------------------------------------

    public function bagiPeran(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        try {
            $r = SesiService::bagiPeran($s);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        }
        $this->audit('bagi_peran', ['sesi' => (int)$s['id']] + $r);
        return $this->ok($r);
    }

    public function fase(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        $aksi = $this->str('aksi');
        try {
            $baru = SesiService::kontrol($s, $aksi, $this->int('detik'));
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        }
        $this->audit('fase_' . $aksi, ['sesi' => (int)$s['id'], 'fase' => $baru['fase_aktif']]);
        return $this->ok(['status' => $baru['status'], 'fase' => $baru['fase_aktif']]);
    }

    public function delegasi(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        try {
            SesiService::setDelegasi((int)$s['id'], $this->int('user_id'), (bool)$this->input('on', true));
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        }
        return $this->ok();
    }

    public function snapshot(int|string $id): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        $teks = ResolusiService::snapshot((int)$s['id'], (int)$u['id']);
        return $this->ok(['teks' => $teks]);
    }

    // --- Penilaian ------------------------------------------------------------------------

    public function hitungSkor(int|string $id): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        set_time_limit(300);
        try {
            ScoringService::hitung($s);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        }
        $this->audit('skor_ai', ['sesi' => (int)$s['id']]);
        return $this->ok(['skor' => $this->ringkasSkor((int)$s['id'])]);
    }

    private function ringkasSkor(int $sesiId): array
    {
        $out = [];
        foreach (ScoringService::terbaru($sesiId) as $p => $r) {
            $out[$p] = [
                'A' => (float)$r['skor_analisis'], 'B' => (float)$r['skor_negosiasi'], 'C' => (float)$r['skor_nilai'],
                'total' => (float)$r['skor_total_ai'], 'model' => $r['model'], 'bukti_terbatas' => (bool)$r['bukti_terbatas'],
                'rasional' => ['A' => $r['detail']['A'] ?? null, 'B' => $r['detail']['B'] ?? null, 'C' => $r['detail']['C'] ?? null],
                'dihitung' => $r['created_at'],
            ];
        }
        return $out;
    }

    /** Ringkasan penilaian: skor AI terbaru + nilai final kelompok. */
    public function penilaian(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        session_write_close();
        $final = [];
        foreach (NilaiService::kelompok((int)$s['id']) as $p => $r) {
            $final[$p] = ['A' => (float)$r['skor_analisis'], 'B' => (float)$r['skor_negosiasi'], 'C' => (float)$r['skor_nilai'],
                'total' => (float)$r['skor_total'], 'catatan' => $r['catatan_dosen'], 'sumber' => $r['sumber'], 'waktu' => $r['ditetapkan_at']];
        }
        return $this->ok(['skor_ai' => $this->ringkasSkor((int)$s['id']), 'final' => $final, 'status' => $s['status'],
            'nilai_publik' => (bool)$s['nilai_publik'], 'nilai_publik_at' => $s['nilai_publik_at']]);
    }

    public function publikasi(int|string $id): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        try {
            NilaiService::publikasi($s, (bool)$this->input('on', true), (int)$u['id']);
        } catch (DomainException $e) {
            return $this->fail($e->getMessage(), 409);
        }
        return $this->ok(['nilai_publik' => (bool)$this->input('on', true)]);
    }

    public function nilaiFinal(int|string $id): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        try {
            $r = NilaiService::tetapkanKelompok($s, $this->str('peran'),
                ['A' => $this->input('A'), 'B' => $this->input('B'), 'C' => $this->input('C')], $this->str('catatan'), (int)$u['id']);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        $this->audit('nilai_final', ['sesi' => (int)$s['id'], 'peran' => $this->str('peran')] + $r);
        return $this->ok($r);
    }

    public function individu(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        session_write_close();
        return $this->ok(['rows' => NilaiService::individu((int)$s['id']), 'batas' => config('nilai.batas_individu')]);
    }

    public function setIndividu(int|string $id, int|string $uid): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $this->verifyCsrf();
        $v = $this->input('nilai');
        if (!is_numeric($v)) return $this->fail('Nilai harus berupa angka.', 422);
        try {
            $r = NilaiService::setIndividu($s, (int)$uid, (float)$v, $this->str('alasan'), (int)$u['id']);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        return $this->ok($r);
    }

    public function exportCsv(int|string $id): string
    {
        [, $s] = $this->dosenSesi((int)$id);
        $level = ($_GET['level'] ?? '') === 'individu' ? 'individu' : 'kelompok';
        $rows = $level === 'individu' ? NilaiService::csvIndividu((int)$s['id']) : NilaiService::csvKelompok((int)$s['id']);
        return $this->csv("cakaproom_sesi{$s['id']}_nilai_{$level}.csv", $rows);
    }

    /** Transkrip lengkap per fase + Joint Resolution final. */
    public function transkrip(int|string $id): string
    {
        [$u, $s] = $this->dosenSesi((int)$id);
        $kasus = $this->kasus((int)$s['kasus_id']);
        return $this->render('dosen/transkrip', [
            'title' => 'Transkrip — CAKAPROOM', 'u' => $u, 'sesi' => $s, 'kasus' => $kasus,
            'posts' => StateService::semuaPost((int)$s['id']),
            'resolusi' => Database::value('SELECT teks_final FROM cr_resolusi WHERE sesi_id = ?', [$s['id']]),
            'klausul' => ResolusiService::klausul((int)$s['id'], (int)$s['kasus_id']),
            'sikap' => ResolusiService::sikap((int)$s['id']),
        ]);
    }
}
