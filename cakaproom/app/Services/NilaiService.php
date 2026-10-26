<?php
namespace App\Services;

use App\Core\Database;

/**
 * Nilai final KELOMPOK (cr_nilai_final) dan penyesuaian nilai INDIVIDUAL
 * (cr_nilai_individu, keputusan #13). Total selalu dihitung server (35/35/30).
 * Nilai dosen tersimpan terpisah dari skor AI.
 */
class NilaiService
{
    /** @param array{A:float,B:float,C:float} $skor */
    public static function tetapkanKelompok(array $sesi, string $peran, array $skor, string $catatan, int $dosenId): array
    {
        if (!in_array($sesi['status'], ['selesai', 'dinilai'], true)) {
            throw new \DomainException('Nilai hanya dapat ditetapkan setelah sesi diakhiri.');
        }
        if (!in_array($peran, SesiService::KODE_PERAN, true)) {
            throw new \DomainException('Peran tidak valid.');
        }
        foreach (['A', 'B', 'C'] as $k) {
            if (!isset($skor[$k]) || !is_numeric($skor[$k]) || $skor[$k] < 0 || $skor[$k] > 100) {
                throw new \DomainException("Skor {$k} harus berupa angka 0–100.");
            }
            $skor[$k] = round((float)$skor[$k], 2);
        }
        $total = ScoringService::total($skor['A'], $skor['B'], $skor['C']);

        $ai = ScoringService::terbaru((int)$sesi['id'])[$peran] ?? null;
        $sumber = ($ai && (float)$ai['skor_analisis'] === $skor['A'] && (float)$ai['skor_negosiasi'] === $skor['B'] && (float)$ai['skor_nilai'] === $skor['C'])
            ? 'ai' : 'override';

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO cr_nilai_final (sesi_id, kode_peran, skor_analisis, skor_negosiasi, skor_nilai, skor_total, catatan_dosen, ditetapkan_oleh, ditetapkan_at, sumber)
                 VALUES (?,?,?,?,?,?,?,?,NOW(),?)
                 ON DUPLICATE KEY UPDATE skor_analisis=VALUES(skor_analisis), skor_negosiasi=VALUES(skor_negosiasi), skor_nilai=VALUES(skor_nilai),
                   skor_total=VALUES(skor_total), catatan_dosen=VALUES(catatan_dosen), ditetapkan_oleh=VALUES(ditetapkan_oleh),
                   ditetapkan_at=NOW(), sumber=VALUES(sumber)',
                [$sesi['id'], $peran, $skor['A'], $skor['B'], $skor['C'], $total, mb_substr($catatan, 0, 2000), $dosenId, $sumber]);

            // Baris nilai individual: default = nilai kelompok; penyesuaian dosen dipertahankan.
            $anggota = Database::all('SELECT user_id FROM cr_peserta_sesi WHERE sesi_id = ? AND kode_peran = ?', [$sesi['id'], $peran]);
            foreach ($anggota as $a) {
                Database::run(
                    'INSERT INTO cr_nilai_individu (sesi_id, user_id, kode_peran, nilai_kelompok, nilai_individu, selisih, ditetapkan_oleh)
                     VALUES (?,?,?,?,?,0,?)
                     ON DUPLICATE KEY UPDATE
                       nilai_individu = IF(selisih = 0, VALUES(nilai_kelompok), nilai_individu),
                       nilai_kelompok = VALUES(nilai_kelompok),
                       selisih = IF(selisih = 0, 0, nilai_individu - VALUES(nilai_kelompok))',
                    [$sesi['id'], $a['user_id'], $peran, $total, $total, $dosenId]);
            }
            $n = (int)Database::value('SELECT COUNT(*) FROM cr_nilai_final WHERE sesi_id = ?', [$sesi['id']]);
            if ($n >= 4) {
                Database::run("UPDATE cr_sesi SET status = 'dinilai' WHERE id = ?", [$sesi['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['total' => $total, 'sumber' => $sumber];
    }

    /** Dosen mempublikasikan / menarik kembali nilai ke mahasiswa. */
    public static function publikasi(array $sesi, bool $on, int $dosenId): void
    {
        if ($on && $sesi['status'] !== 'dinilai') {
            throw new DomainException('Tetapkan nilai final keempat kelompok terlebih dahulu.');
        }
        Database::run('UPDATE cr_sesi SET nilai_publik = ?, nilai_publik_at = ? WHERE id = ?',
            [$on ? 1 : 0, $on ? date('Y-m-d H:i:s') : null, $sesi['id']]);
        Database::insert('cr_audit_log', ['user_id' => $dosenId, 'aksi' => $on ? 'nilai_publikasi' : 'nilai_tarik',
            'detail' => json_encode(['sesi' => (int)$sesi['id']])]);
    }

    /**
     * Yang boleh dilihat mahasiswa setelah dipublikasikan: nilai KELOMPOK perannya sendiri
     * (+ catatan dosen) dan nilai INDIVIDUNYA sendiri. Tidak ada: skor AI, nilai peran lain,
     * nilai teman, dan alasan penyesuaian individual.
     */
    public static function untukMahasiswa(int $sesiId, int $userId, ?string $peran): ?array
    {
        if (!$peran) return null;
        $k = Database::one('SELECT * FROM cr_nilai_final WHERE sesi_id = ? AND kode_peran = ?', [$sesiId, $peran]);
        $i = Database::one('SELECT nilai_kelompok, nilai_individu, selisih FROM cr_nilai_individu WHERE sesi_id = ? AND user_id = ?', [$sesiId, $userId]);
        if (!$k) return null;
        return [
            'peran' => $peran,
            'kelompok' => ['A' => (float)$k['skor_analisis'], 'B' => (float)$k['skor_negosiasi'], 'C' => (float)$k['skor_nilai'],
                'total' => (float)$k['skor_total'], 'catatan' => $k['catatan_dosen']],
            'individu' => $i ? ['nilai' => (float)$i['nilai_individu'], 'nilai_kelompok' => (float)$i['nilai_kelompok'], 'selisih' => (float)$i['selisih']] : null,
        ];
    }

    public static function kelompok(int $sesiId): array
    {
        $out = [];
        foreach (Database::all('SELECT * FROM cr_nilai_final WHERE sesi_id = ?', [$sesiId]) as $r) {
            $out[$r['kode_peran']] = $r;
        }
        return $out;
    }

    /** Ubah nilai individual satu mahasiswa; alasan wajib bila beda dari nilai kelompok. */
    public static function setIndividu(array $sesi, int $userId, float $nilai, string $alasan, int $dosenId): array
    {
        $row = Database::one('SELECT * FROM cr_nilai_individu WHERE sesi_id = ? AND user_id = ?', [$sesi['id'], $userId]);
        if (!$row) {
            throw new \DomainException('Tetapkan nilai final kelompok terlebih dahulu.');
        }
        if ($nilai < 0 || $nilai > 100) {
            throw new \DomainException('Nilai individu harus 0–100.');
        }
        $nilai = round($nilai, 2);
        $selisih = round($nilai - (float)$row['nilai_kelompok'], 2);
        $alasan = trim($alasan);
        if ($selisih != 0 && $alasan === '') {
            throw new \DomainException('Alasan wajib diisi bila nilai berbeda dari nilai kelompok.');
        }
        $batas = config('nilai.batas_individu');
        if ($batas !== null && abs($selisih) > (float)$batas) {
            throw new \DomainException("Penyesuaian maksimal ±{$batas} poin dari nilai kelompok.");
        }
        Database::run(
            'UPDATE cr_nilai_individu SET nilai_individu = ?, selisih = ?, alasan = ?, ditetapkan_oleh = ?, ditetapkan_at = NOW() WHERE sesi_id = ? AND user_id = ?',
            [$nilai, $selisih, $selisih == 0 ? null : mb_substr($alasan, 0, 2000), $dosenId, $sesi['id'], $userId]);
        Database::insert('cr_audit_log', [
            'user_id' => $dosenId, 'aksi' => 'nilai_individu',
            'detail'  => json_encode(['sesi' => (int)$sesi['id'], 'mahasiswa' => $userId, 'dari' => (float)$row['nilai_individu'],
                'ke' => $nilai, 'nilai_kelompok' => (float)$row['nilai_kelompok'], 'alasan' => $alasan], JSON_UNESCAPED_UNICODE),
        ]);
        return ['nilai_individu' => $nilai, 'selisih' => $selisih];
    }

    /** Tabel mahasiswa + statistik partisipasi + nilai individual. */
    public static function individu(int $sesiId): array
    {
        return Database::all(
            "SELECT p.user_id, u.nama, u.nim, p.kode_peran, p.is_delegasi,
                    SUM(po.tipe = 'argumen') AS n_argumen, SUM(po.tipe = 'pertanyaan') AS n_pertanyaan,
                    SUM(po.tipe = 'jawaban') AS n_jawaban, SUM(po.tipe = 'negosiasi') AS n_negosiasi,
                    ni.nilai_kelompok, ni.nilai_individu, ni.selisih, ni.alasan
               FROM cr_peserta_sesi p
               JOIN cr_users u ON u.id = p.user_id
               LEFT JOIN cr_post po ON po.sesi_id = p.sesi_id AND po.user_id = p.user_id
               LEFT JOIN cr_nilai_individu ni ON ni.sesi_id = p.sesi_id AND ni.user_id = p.user_id
              WHERE p.sesi_id = ? AND p.kode_peran IS NOT NULL
              GROUP BY p.user_id, u.nama, u.nim, p.kode_peran, p.is_delegasi, ni.nilai_kelompok, ni.nilai_individu, ni.selisih, ni.alasan
              ORDER BY p.kode_peran, u.nama", [$sesiId]);
    }

    public static function csvKelompok(int $sesiId): array
    {
        $rows = [['Peran', 'Skor Analisis', 'Skor Negosiasi', 'Skor Nilai Kebangsaan', 'Total', 'Sumber', 'Catatan dosen', 'Ditetapkan']];
        $n = self::kelompok($sesiId);
        foreach (SesiService::KODE_PERAN as $p) {
            if (!isset($n[$p])) { $rows[] = [peran_label($p), '', '', '', '', 'belum ditetapkan', '', '']; continue; }
            $r = $n[$p];
            $rows[] = [peran_label($p), $r['skor_analisis'], $r['skor_negosiasi'], $r['skor_nilai'], $r['skor_total'], $r['sumber'], $r['catatan_dosen'], $r['ditetapkan_at']];
        }
        return $rows;
    }

    public static function csvIndividu(int $sesiId): array
    {
        $rows = [['NIM', 'Nama', 'Kelompok', 'Delegasi', 'Argumen', 'Pertanyaan', 'Jawaban', 'Negosiasi', 'Nilai kelompok', 'Nilai individu', 'Selisih', 'Alasan']];
        foreach (self::individu($sesiId) as $r) {
            $rows[] = [$r['nim'], $r['nama'], peran_label($r['kode_peran']), $r['is_delegasi'] ? 'ya' : '',
                $r['n_argumen'], $r['n_pertanyaan'], $r['n_jawaban'], $r['n_negosiasi'],
                $r['nilai_kelompok'], $r['nilai_individu'], $r['selisih'], $r['alasan']];
        }
        return $rows;
    }
}
