<?php
namespace App\Services;

use App\Core\Database;

/**
 * Sumber kebenaran state sesi untuk klien (adapter real-time: polling).
 * Klien mengirim kursor: since (id post terakhir), rev (rev sesi), res_rev.
 * Server hanya mengirim delta -> payload kecil, aman untuk ±75 pengguna.
 *
 * Adapter pub/sub (Pusher/Ably/WebSocket) di masa depan cukup memberi tahu
 * klien "ada perubahan, panggil state" — kontrak endpoint ini tidak berubah.
 */
class StateService
{
    /**
     * @param array      $sesi    baris cr_sesi (segar dari DB)
     * @param array|null $peserta baris cr_peserta_sesi (null = dosen/admin)
     */
    public static function build(array $sesi, ?array $peserta, int $since, int $revKlien, int $resRevKlien, bool $dosen): array
    {
        $id = (int)$sesi['id'];
        $out = [
            'ok' => true,
            'sesi' => [
                'id' => $id, 'status' => $sesi['status'], 'fase' => $sesi['fase_aktif'],
                'fase_label' => FASE_LABEL[$sesi['fase_aktif']] ?? $sesi['fase_aktif'],
                'sisa' => SesiService::sisa($sesi), 'dijeda' => SesiService::dijeda($sesi),
                'rev' => (int)$sesi['rev'], 'res_rev' => (int)$sesi['res_rev'],
                'word_min' => (int)$sesi['word_min'], 'word_max' => (int)$sesi['word_max'],
                'peran_dibagi' => (bool)$sesi['peran_dibagi'],
                'nilai_publik' => (bool)$sesi['nilai_publik'],
            ],
        ];

        if ($peserta) {
            $out['me'] = [
                'user_id' => (int)$peserta['user_id'], 'peran' => $peserta['kode_peran'],
                'delegasi' => (bool)$peserta['is_delegasi'],
                'bisa_sunting' => ResolusiService::bisaMenyunting($sesi, $peserta),
            ];
        }

        // Nilai hanya bila dosen sudah mempublikasikan; hanya milik mahasiswa itu sendiri.
        if ($peserta && (int)$sesi['nilai_publik'] === 1) {
            $out['nilai'] = NilaiService::untukMahasiswa($id, (int)$peserta['user_id'], $peserta['kode_peran']);
        }

        // Daftar anggota (nama) — dikirim hanya bila rev berubah.
        $perluDaftar = (int)$sesi['rev'] !== $revKlien;
        if ($perluDaftar) {
            $out['peserta'] = self::daftarPeserta($id, $peserta, $dosen);
        }

        // Post baru sejak kursor.
        $posts = Database::all('SELECT * FROM cr_post WHERE sesi_id = ? AND id > ? ORDER BY id ASC LIMIT 200', [$id, $since]);
        if ($posts) {
            $nama = self::namaMap(array_unique(array_column($posts, 'user_id')));
            $out['posts'] = array_map(fn($p) => PostService::tampil($p, $nama), $posts);
        }

        // Joint Resolution: kunci selalu dikirim (kecil); teks hanya bila berubah.
        if (in_array($sesi['fase_aktif'], ['negosiasi', 'penutupan'], true) || $sesi['status'] !== 'lobby' && $sesi['status'] !== 'berjalan') {
            $perluTeks = (int)$sesi['res_rev'] !== $resRevKlien;
            $out['resolusi'] = [
                'klausul' => ResolusiService::klausul($id, (int)$sesi['kasus_id'], $perluTeks),
                'teks_dikirim' => $perluTeks,
            ];
            if ($perluTeks) {
                $out['resolusi']['sikap'] = ResolusiService::sikap($id);
            }
        }

        if ($dosen) {
            $out['stat'] = SesiService::statistik($sesi);
        }
        return $out;
    }

    private static function daftarPeserta(int $sesiId, ?array $peserta, bool $dosen): array
    {
        $rows = Database::all(
            'SELECT p.user_id, p.kode_peran, p.is_delegasi, u.nama, u.nim
               FROM cr_peserta_sesi p JOIN cr_users u ON u.id = p.user_id
              WHERE p.sesi_id = ? ORDER BY u.nama', [$sesiId]);
        $out = ['jumlah' => count($rows)];
        if ($dosen) {
            $out['semua'] = array_map(fn($r) => [
                'user_id' => (int)$r['user_id'], 'nama' => $r['nama'], 'nim' => $r['nim'],
                'peran' => $r['kode_peran'], 'delegasi' => (bool)$r['is_delegasi'],
            ], $rows);
        } elseif ($peserta && $peserta['kode_peran']) {
            // Mahasiswa melihat anggota perannya sendiri + delegasi semua peran.
            $out['tim'] = [];
            $out['delegasi'] = [];
            foreach ($rows as $r) {
                if ($r['kode_peran'] === $peserta['kode_peran']) {
                    $out['tim'][] = ['user_id' => (int)$r['user_id'], 'nama' => $r['nama'], 'delegasi' => (bool)$r['is_delegasi']];
                }
                if ($r['is_delegasi']) {
                    $out['delegasi'][] = ['nama' => $r['nama'], 'peran' => $r['kode_peran']];
                }
            }
        }
        return $out;
    }

    /** @return array<int,string> */
    public static function namaMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $map = [];
        foreach (Database::all("SELECT id, nama FROM cr_users WHERE id IN ($in)", $ids) as $r) {
            $map[(int)$r['id']] = $r['nama'];
        }
        return $map;
    }

    /** Muat ulang seluruh feed (halaman penuh / transkrip). */
    public static function semuaPost(int $sesiId): array
    {
        $posts = Database::all('SELECT * FROM cr_post WHERE sesi_id = ? ORDER BY id ASC', [$sesiId]);
        $nama = self::namaMap(array_unique(array_column($posts, 'user_id')));
        return array_map(fn($p) => PostService::tampil($p, $nama), $posts);
    }
}
