<?php
namespace App\Services;

use App\Core\Database;

/**
 * Joint Resolution — fallback "kunci per klausul" (brief 6.3): satu penyunting
 * aktif per klausul; hanya delegasi (2/peran) yang dapat menyunting; peserta
 * lain melihat live read-only lewat state polling.
 */
class ResolusiService
{
    public static function bisaMenyunting(array $sesi, array $peserta): bool
    {
        return $sesi['status'] === 'berjalan' && !SesiService::dijeda($sesi)
            && $sesi['fase_aktif'] === 'negosiasi' && (int)$peserta['is_delegasi'] === 1;
    }

    /** @return array<int,array<string,mixed>> klausul + status kunci (waktu server) */
    public static function klausul(int $sesiId, int $kasusId, bool $denganTeks = true): array
    {
        $rows = Database::all(
            'SELECT k.no, k.judul, k.deskripsi, r.teks, r.rev, r.editor_id, r.locked_by, r.lock_until, r.updated_at,
                    ue.nama AS editor_nama, ul.nama AS locked_nama, pl.kode_peran AS locked_peran
               FROM cr_kasus_klausul k
               LEFT JOIN cr_resolusi_klausul r ON r.sesi_id = ? AND r.no = k.no
               LEFT JOIN cr_users ue ON ue.id = r.editor_id
               LEFT JOIN cr_users ul ON ul.id = r.locked_by
               LEFT JOIN cr_peserta_sesi pl ON pl.sesi_id = r.sesi_id AND pl.user_id = r.locked_by
              WHERE k.kasus_id = ? ORDER BY k.no', [$sesiId, $kasusId]);
        $out = [];
        $now = time();
        foreach ($rows as $r) {
            $sisaKunci = $r['lock_until'] ? strtotime($r['lock_until']) - $now : 0;
            $aktif = $r['locked_by'] && $sisaKunci > 0;
            $o = [
                'no' => (int)$r['no'], 'judul' => $r['judul'], 'deskripsi' => $r['deskripsi'],
                'rev' => (int)$r['rev'],
                'editor' => $r['editor_nama'],
                'kunci' => $aktif ? ['user_id' => (int)$r['locked_by'], 'nama' => $r['locked_nama'], 'peran' => $r['locked_peran'], 'sisa' => $sisaKunci] : null,
            ];
            if ($denganTeks) $o['teks'] = (string)$r['teks'];
            $out[] = $o;
        }
        return $out;
    }

    public static function sikap(int $sesiId): array
    {
        $out = [];
        foreach (Database::all('SELECT s.kode_peran, s.sikap, s.catatan, u.nama FROM cr_resolusi_sikap s JOIN cr_users u ON u.id = s.user_id WHERE s.sesi_id = ?', [$sesiId]) as $r) {
            $out[$r['kode_peran']] = ['sikap' => $r['sikap'], 'catatan' => $r['catatan'], 'oleh' => $r['nama']];
        }
        return $out;
    }

    /** Ambil / perpanjang kunci klausul. */
    public static function kunci(array $sesi, array $peserta, int $no): array
    {
        if (!self::bisaMenyunting($sesi, $peserta)) {
            return ['ok' => false, 'error' => 'Hanya delegasi yang dapat menyunting pada Fase 3.'];
        }
        $ttl = (int)config('sesi.klausul_lock_detik', 45);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $r = Database::one('SELECT * FROM cr_resolusi_klausul WHERE sesi_id = ? AND no = ? FOR UPDATE', [$sesi['id'], $no]);
            if (!$r) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Klausul tidak ditemukan.']; }
            $dipegangLain = $r['locked_by'] && (int)$r['locked_by'] !== (int)$peserta['user_id']
                && $r['lock_until'] && strtotime($r['lock_until']) > time();
            if ($dipegangLain) {
                $pdo->rollBack();
                $nama = Database::value('SELECT nama FROM cr_users WHERE id = ?', [$r['locked_by']]);
                return ['ok' => false, 'error' => "Klausul sedang disunting oleh {$nama}.", 'terkunci' => true];
            }
            Database::run('UPDATE cr_resolusi_klausul SET locked_by = ?, lock_until = ? WHERE sesi_id = ? AND no = ?',
                [$peserta['user_id'], date('Y-m-d H:i:s', time() + $ttl), $sesi['id'], $no]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'rev' => (int)$r['rev'], 'ttl' => $ttl];
    }

    /** Simpan teks klausul (autosave) — wajib memegang kunci. */
    public static function simpan(array $sesi, array $peserta, int $no, string $teks, bool $lepas): array
    {
        if (!self::bisaMenyunting($sesi, $peserta)) {
            return ['ok' => false, 'error' => 'Hanya delegasi yang dapat menyunting pada Fase 3.'];
        }
        $teks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $teks) ?? '';
        if (mb_strlen($teks) > 8000) {
            return ['ok' => false, 'error' => 'Teks klausul terlalu panjang (maks 8000 karakter).'];
        }
        $ttl = (int)config('sesi.klausul_lock_detik', 45);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $r = Database::one('SELECT * FROM cr_resolusi_klausul WHERE sesi_id = ? AND no = ? FOR UPDATE', [$sesi['id'], $no]);
            if (!$r) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Klausul tidak ditemukan.']; }
            $punya = (int)$r['locked_by'] === (int)$peserta['user_id'] && $r['lock_until'] && strtotime($r['lock_until']) > time();
            if (!$punya) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Kunci klausul hilang/diambil penyunting lain. Ambil kunci lagi.', 'terkunci' => true];
            }
            $berubah = $teks !== (string)$r['teks'];
            Database::run(
                'UPDATE cr_resolusi_klausul SET teks = ?, rev = rev + ?, editor_id = ?, updated_at = NOW(), locked_by = ?, lock_until = ?
                  WHERE sesi_id = ? AND no = ?',
                [$teks, $berubah ? 1 : 0, $peserta['user_id'],
                 $lepas ? null : $peserta['user_id'], $lepas ? null : date('Y-m-d H:i:s', time() + $ttl),
                 $sesi['id'], $no]);
            if ($berubah) {
                Database::run('UPDATE cr_sesi SET res_rev = res_rev + 1 WHERE id = ?', [$sesi['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return ['ok' => true, 'rev' => (int)$r['rev'] + ($berubah ? 1 : 0)];
    }

    /** Sikap peran (setuju / keberatan beralasan) — syarat sah Joint Resolution. */
    public static function setSikap(array $sesi, array $peserta, string $sikap, string $catatan): array
    {
        if (!self::bisaMenyunting($sesi, $peserta)) {
            return ['ok' => false, 'error' => 'Hanya delegasi yang dapat mencatat sikap peran pada Fase 3.'];
        }
        if (!in_array($sikap, ['setuju', 'keberatan'], true)) return ['ok' => false, 'error' => 'Sikap tidak valid.'];
        $catatan = trim($catatan);
        if ($sikap === 'keberatan' && hitung_kata($catatan) < 5) {
            return ['ok' => false, 'error' => 'Keberatan wajib disertai alasan (minimal 5 kata).'];
        }
        Database::run(
            'INSERT INTO cr_resolusi_sikap (sesi_id, kode_peran, sikap, catatan, user_id, updated_at) VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE sikap = VALUES(sikap), catatan = VALUES(catatan), user_id = VALUES(user_id), updated_at = NOW()',
            [$sesi['id'], $peserta['kode_peran'], $sikap, mb_substr($catatan, 0, 1000), $peserta['user_id']]);
        Database::run('UPDATE cr_sesi SET res_rev = res_rev + 1 WHERE id = ?', [$sesi['id']]);
        return ['ok' => true];
    }

    /** Status syarat sah: semua klausul terisi + tiap peran punya sikap. */
    public static function syaratSah(int $sesiId, int $kasusId): array
    {
        $kl = self::klausul($sesiId, $kasusId);
        $kosong = array_values(array_map(fn($k) => $k['no'], array_filter($kl, fn($k) => trim($k['teks']) === '')));
        $sikap = self::sikap($sesiId);
        $tanpaSikap = array_values(array_diff(SesiService::KODE_PERAN, array_keys($sikap)));
        return ['sah' => !$kosong && !$tanpaSikap && $kl, 'klausul_kosong' => $kosong, 'peran_tanpa_sikap' => $tanpaSikap];
    }

    /** Susun teks final Joint Resolution dan simpan sebagai snapshot. */
    public static function snapshot(int $sesiId, ?int $oleh): string
    {
        $sesi = SesiService::find($sesiId);
        $kasus = Database::one('SELECT judul FROM cr_kasus WHERE id = ?', [$sesi['kasus_id']]);
        $kl = self::klausul($sesiId, (int)$sesi['kasus_id']);
        $out = "JOINT RESOLUTION — {$kasus['judul']}\n\n";
        foreach ($kl as $k) {
            $isi = trim($k['teks']) !== '' ? trim($k['teks']) : '(belum diisi)';
            $out .= "Klausul {$k['no']} — {$k['judul']}\n{$isi}\n\n";
        }
        $out .= "Sikap tiap peran:\n";
        $sikap = self::sikap($sesiId);
        foreach (SesiService::KODE_PERAN as $p) {
            $s = $sikap[$p] ?? null;
            $out .= '- ' . peran_label($p) . ': ' . ($s ? $s['sikap'] . ($s['catatan'] ? ' — ' . $s['catatan'] : '') : '(belum mencatat)') . "\n";
        }
        Database::run('UPDATE cr_resolusi SET teks_final = ?, snapshot_at = NOW(), final_by = ? WHERE sesi_id = ?', [$out, $oleh, $sesiId]);
        return $out;
    }
}
