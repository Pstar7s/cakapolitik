<?php
namespace App\Services;

use App\Core\Database;

/**
 * Siklus sesi: pembuatan, lobby, pembagian peran otomatis, fase & timer.
 * Backend = sumber kebenaran; timer memakai waktu server (klien hanya menerima
 * "sisa detik" dari server).
 */
class SesiService
{
    public const KODE_PERAN = ['PEMERINTAH', 'SIPIL', 'SWASTA', 'WARGA'];
    public const URUTAN_FASE = ['lobby', 'argumen', 'uji_silang', 'negosiasi', 'penutupan'];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM cr_sesi WHERE id = ?', [$id]);
    }

    public static function bump(int $sesiId): void
    {
        Database::run('UPDATE cr_sesi SET rev = rev + 1 WHERE id = ?', [$sesiId]);
    }

    public static function durasi(array $sesi): array
    {
        $d = json_decode($sesi['durasi_fase'] ?? '', true);
        return is_array($d) ? $d + config('sesi.durasi') : config('sesi.durasi');
    }

    // --- Pembuatan -----------------------------------------------------------

    public static function create(int $kelasId, int $kasusId, string $judul, int $userId, array $opts = []): int
    {
        $cfg   = config('sesi');
        $durasi = $cfg['durasi'];
        foreach (($opts['durasi_menit'] ?? []) as $fase => $menit) {
            if (isset($durasi[$fase]) && is_numeric($menit) && $menit > 0) {
                $durasi[$fase] = (int)round($menit * 60);
            }
        }
        $wmin = max(1, (int)($opts['word_min'] ?? $cfg['word_min']));
        $wmax = max($wmin, (int)($opts['word_max'] ?? $cfg['word_max']));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::insert('cr_sesi', [
                'kelas_id'    => $kelasId,
                'kasus_id'    => $kasusId,
                'judul'       => $judul,
                'status'      => 'lobby',
                'fase_aktif'  => 'lobby',
                'durasi_fase' => json_encode($durasi),
                'word_min'    => $wmin,
                'word_max'    => $wmax,
                'created_by'  => $userId,
            ]);
            Database::insert('cr_resolusi', ['sesi_id' => $id]);
            $klausul = Database::all('SELECT no FROM cr_kasus_klausul WHERE kasus_id = ? ORDER BY no', [$kasusId]);
            foreach ($klausul as $k) {
                Database::insert('cr_resolusi_klausul', ['sesi_id' => $id, 'no' => (int)$k['no'], 'teks' => '']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $id;
    }

    // --- Peserta ---------------------------------------------------------------

    /** Mahasiswa kelas ini? */
    public static function dalamKelas(int $kelasId, int $userId): bool
    {
        return (bool)Database::value('SELECT 1 FROM cr_kelas_mahasiswa WHERE kelas_id = ? AND user_id = ?', [$kelasId, $userId]);
    }

    public static function peserta(int $sesiId, int $userId): ?array
    {
        return Database::one('SELECT * FROM cr_peserta_sesi WHERE sesi_id = ? AND user_id = ?', [$sesiId, $userId]);
    }

    /**
     * Masuk lobby. Peran yang sudah dibagi TIDAK berubah (reconnect aman);
     * peserta terlambat otomatis mendapat peran dengan anggota paling sedikit.
     */
    public static function join(array $sesi, int $userId): array
    {
        if (!in_array($sesi['status'], ['lobby', 'berjalan'], true)) {
            throw new \DomainException('Sesi ini sudah tidak menerima peserta.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Kunci baris sesi agar penetapan peran peserta terlambat serial.
            Database::one('SELECT id FROM cr_sesi WHERE id = ? FOR UPDATE', [$sesi['id']]);
            $p = Database::one('SELECT * FROM cr_peserta_sesi WHERE sesi_id = ? AND user_id = ?', [$sesi['id'], $userId]);
            if (!$p) {
                Database::run('INSERT INTO cr_peserta_sesi (sesi_id, user_id, last_seen) VALUES (?, ?, NOW())', [$sesi['id'], $userId]);
                $cur = Database::one('SELECT peran_dibagi FROM cr_sesi WHERE id = ?', [$sesi['id']]);
                if ((int)$cur['peran_dibagi'] === 1) {
                    self::assignUnassigned((int)$sesi['id']);
                }
                self::bump((int)$sesi['id']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return self::peserta((int)$sesi['id'], $userId);
    }

    // --- Pembagian peran ---------------------------------------------------------

    /** Hitung anggota per peran. */
    public static function hitungPeran(int $sesiId): array
    {
        $out = array_fill_keys(self::KODE_PERAN, 0);
        foreach (Database::all('SELECT kode_peran, COUNT(*) c FROM cr_peserta_sesi WHERE sesi_id = ? AND kode_peran IS NOT NULL GROUP BY kode_peran', [$sesiId]) as $r) {
            $out[$r['kode_peran']] = (int)$r['c'];
        }
        return $out;
    }

    /**
     * Bagikan peran ke semua peserta yang belum punya peran: acak, selisih
     * antar-peran maksimal 1. Dipanggil dalam transaksi pemanggil.
     */
    private static function assignUnassigned(int $sesiId): int
    {
        $users = array_column(Database::all(
            'SELECT user_id FROM cr_peserta_sesi WHERE sesi_id = ? AND kode_peran IS NULL', [$sesiId]), 'user_id');
        if (!$users) return 0;
        shuffle($users);

        $count = self::hitungPeran($sesiId);
        foreach ($users as $uid) {
            // peran dengan anggota paling sedikit; seri dipilih acak
            $min = min($count);
            $kandidat = array_keys(array_filter($count, fn($c) => $c === $min));
            $pilih = $kandidat[array_rand($kandidat)];
            Database::run('UPDATE cr_peserta_sesi SET kode_peran = ? WHERE sesi_id = ? AND user_id = ?', [$pilih, $sesiId, $uid]);
            $count[$pilih]++;
        }
        self::lengkapiDelegasi($sesiId);
        return count($users);
    }

    /** Pastikan tiap peran punya N delegasi (mode 'acak'; mode 'dosen' = manual). */
    private static function lengkapiDelegasi(int $sesiId): void
    {
        if (config('sesi.delegasi_mode') !== 'acak') return;
        $n = (int)config('sesi.delegasi_per_peran', 2);
        foreach (self::KODE_PERAN as $peran) {
            $ada = (int)Database::value('SELECT COUNT(*) FROM cr_peserta_sesi WHERE sesi_id = ? AND kode_peran = ? AND is_delegasi = 1', [$sesiId, $peran]);
            if ($ada >= $n) continue;
            Database::run(
                'UPDATE cr_peserta_sesi SET is_delegasi = 1
                  WHERE sesi_id = ? AND kode_peran = ? AND is_delegasi = 0 ORDER BY RAND() LIMIT ' . ($n - $ada),
                [$sesiId, $peran]
            );
        }
    }

    public static function bagiPeran(array $sesi): array
    {
        if ($sesi['status'] !== 'lobby' && $sesi['status'] !== 'berjalan') {
            throw new \DomainException('Sesi tidak berada pada status yang memungkinkan pembagian peran.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::one('SELECT id FROM cr_sesi WHERE id = ? FOR UPDATE', [$sesi['id']]);
            $baru = self::assignUnassigned((int)$sesi['id']);
            Database::run('UPDATE cr_sesi SET peran_dibagi = 1 WHERE id = ?', [$sesi['id']]);
            self::bump((int)$sesi['id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['dibagi' => $baru, 'per_peran' => self::hitungPeran((int)$sesi['id'])];
    }

    /** Dosen menunjuk/mencabut delegasi (mode manual atau koreksi). */
    public static function setDelegasi(int $sesiId, int $userId, bool $on): void
    {
        $p = self::peserta($sesiId, $userId);
        if (!$p || !$p['kode_peran']) {
            throw new \DomainException('Peserta belum punya peran.');
        }
        if ($on) {
            $batas = (int)config('sesi.delegasi_per_peran', 2);
            $ada = (int)Database::value('SELECT COUNT(*) FROM cr_peserta_sesi WHERE sesi_id = ? AND kode_peran = ? AND is_delegasi = 1 AND user_id <> ?',
                [$sesiId, $p['kode_peran'], $userId]);
            if ($ada >= $batas) {
                throw new \DomainException("Peran {$p['kode_peran']} sudah punya {$batas} delegasi. Cabut salah satu dahulu.");
            }
        }
        Database::run('UPDATE cr_peserta_sesi SET is_delegasi = ? WHERE sesi_id = ? AND user_id = ?', [$on ? 1 : 0, $sesiId, $userId]);
        self::bump($sesiId);
    }

    // --- Fase & timer --------------------------------------------------------------

    /** Sisa detik fase aktif (null bila tidak berlaku). */
    public static function sisa(array $sesi): ?int
    {
        if ($sesi['status'] !== 'berjalan') return null;
        if ($sesi['jeda_sisa'] !== null) return max(0, (int)$sesi['jeda_sisa']);
        if (!$sesi['fase_berakhir_at']) return null;
        return strtotime($sesi['fase_berakhir_at']) - time();   // boleh negatif = lewat waktu
    }

    public static function dijeda(array $sesi): bool
    {
        return $sesi['status'] === 'berjalan' && $sesi['jeda_sisa'] !== null;
    }

    /**
     * Kontrol dosen. aksi: mulai | jeda | lanjut | tambah | berikutnya | akhiri
     * @param int $detik   untuk 'tambah' (boleh negatif = kurangi)
     */
    public static function kontrol(array $sesi, string $aksi, int $detik = 0): array
    {
        $id = (int)$sesi['id'];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $s = Database::one('SELECT * FROM cr_sesi WHERE id = ? FOR UPDATE', [$id]);
            $durasi = self::durasi($s);

            switch ($aksi) {
                case 'mulai':
                    if ($s['status'] !== 'lobby') throw new \DomainException('Sesi sudah dimulai.');
                    if (!(int)$s['peran_dibagi']) throw new \DomainException('Bagi peran terlebih dahulu.');
                    self::setFase($id, 'argumen', $durasi['argumen'], 'berjalan');
                    break;

                case 'jeda':
                    if ($s['status'] !== 'berjalan' || $s['jeda_sisa'] !== null) throw new \DomainException('Sesi tidak sedang berjalan.');
                    $sisa = max(0, strtotime($s['fase_berakhir_at']) - time());
                    Database::run('UPDATE cr_sesi SET jeda_sisa = ? WHERE id = ?', [$sisa, $id]);
                    break;

                case 'lanjut':
                    if ($s['status'] !== 'berjalan' || $s['jeda_sisa'] === null) throw new \DomainException('Sesi tidak sedang dijeda.');
                    Database::run('UPDATE cr_sesi SET fase_berakhir_at = ?, jeda_sisa = NULL WHERE id = ?',
                        [date('Y-m-d H:i:s', time() + (int)$s['jeda_sisa']), $id]);
                    break;

                case 'tambah':
                    if ($s['status'] !== 'berjalan') throw new \DomainException('Sesi tidak sedang berjalan.');
                    $detik = max(-3600, min(3600, $detik));
                    if ($s['jeda_sisa'] !== null) {
                        Database::run('UPDATE cr_sesi SET jeda_sisa = GREATEST(0, jeda_sisa + ?) WHERE id = ?', [$detik, $id]);
                    } else {
                        Database::run('UPDATE cr_sesi SET fase_berakhir_at = ? WHERE id = ?',
                            [date('Y-m-d H:i:s', strtotime($s['fase_berakhir_at']) + $detik), $id]);
                    }
                    break;

                case 'berikutnya':
                    if ($s['status'] !== 'berjalan') throw new \DomainException('Sesi tidak sedang berjalan.');
                    $i = array_search($s['fase_aktif'], self::URUTAN_FASE, true);
                    $next = self::URUTAN_FASE[$i + 1] ?? null;
                    if (!$next) throw new \DomainException('Sudah di fase terakhir. Gunakan "Akhiri sesi".');
                    self::setFase($id, $next, $durasi[$next], 'berjalan');
                    break;

                case 'akhiri':
                    if (!in_array($s['status'], ['berjalan', 'lobby'], true)) throw new \DomainException('Sesi sudah berakhir.');
                    Database::run(
                        "UPDATE cr_sesi SET status = 'selesai', fase_aktif = 'penutupan', fase_berakhir_at = NULL, jeda_sisa = NULL, ended_at = NOW() WHERE id = ?", [$id]);
                    ResolusiService::snapshot($id, null);
                    break;

                default:
                    throw new \DomainException('Aksi tidak dikenal.');
            }
            self::bump($id);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return self::find($id);
    }

    private static function setFase(int $id, string $fase, int $detik, string $status): void
    {
        Database::run(
            'UPDATE cr_sesi SET status = ?, fase_aktif = ?, fase_mulai_at = NOW(), fase_berakhir_at = ?, jeda_sisa = NULL WHERE id = ?',
            [$status, $fase, date('Y-m-d H:i:s', time() + $detik), $id]
        );
    }

    // --- Statistik ---------------------------------------------------------------------

    public static function statistik(array $sesi): array
    {
        $id = (int)$sesi['id'];
        $win = (int)config('sesi.online_window_detik', 12);
        $online = (int)Database::value(
            'SELECT COUNT(*) FROM cr_peserta_sesi WHERE sesi_id = ? AND last_seen > (NOW() - INTERVAL ' . $win . ' SECOND)', [$id]);
        $total = (int)Database::value('SELECT COUNT(*) FROM cr_peserta_sesi WHERE sesi_id = ?', [$id]);
        $roster = (int)Database::value('SELECT COUNT(*) FROM cr_kelas_mahasiswa WHERE kelas_id = ?', [$sesi['kelas_id']]);

        $post = [];
        foreach (Database::all('SELECT kode_peran, tipe, COUNT(*) c FROM cr_post WHERE sesi_id = ? GROUP BY kode_peran, tipe', [$id]) as $r) {
            $post[$r['kode_peran']][$r['tipe']] = (int)$r['c'];
        }
        $per = [];
        foreach (self::hitungPeran($id) as $kode => $n) {
            $per[$kode] = ['anggota' => $n, 'posts' => $post[$kode] ?? []];
        }
        return ['online' => $online, 'bergabung' => $total, 'roster' => $roster, 'per_peran' => $per];
    }
}
