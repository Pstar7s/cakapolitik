<?php
namespace App\Models;

use App\Core\Database;

/**
 * Pejabat eksekutif & anggota legislatif, plus peta dapil berjenjang
 * (tabel `wilayah_dapil`). Dipakai BRONE untuk menyapa warga dengan
 * daftar wakil sesuai alamat. Lihat database/pejabat.sql.
 */
class Pejabat extends Model
{
    protected static string $table = 'pejabat';

    public static function available(): bool
    {
        try {
            return (int)Database::value('SELECT COUNT(*) FROM pejabat LIMIT 1') > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Peta dapil untuk sebuah kecamatan (atau kab/kota bila rincian kecamatan
     * belum ada). $kecKode / $kabKode = kode Kemendagri; fallback ke nama.
     */
    public static function petaDapil(?string $kabKode, ?string $kecKode, ?string $kabNama, ?string $kecNama): ?array
    {
        if ($kecKode) {
            $row = Database::one('SELECT * FROM wilayah_dapil WHERE kecamatan_kode = ?', [$kecKode]);
            if ($row) return $row;
        }
        if ($kabKode) {
            $row = Database::one(
                'SELECT * FROM wilayah_dapil WHERE kab_kota_kode = ? ORDER BY kecamatan_kode IS NOT NULL DESC LIMIT 1',
                [$kabKode]
            );
            if ($row) return $row;
        }
        if ($kecNama) {
            $row = Database::one(
                'SELECT * FROM wilayah_dapil WHERE LOWER(kecamatan_nama) = LOWER(?)',
                [trim($kecNama)]
            );
            if ($row) return $row;
        }
        if ($kabNama) {
            $row = Database::one(
                'SELECT * FROM wilayah_dapil WHERE LOWER(kab_kota_nama) = LOWER(?) ORDER BY kecamatan_kode IS NOT NULL DESC LIMIT 1',
                [trim($kabNama)]
            );
            if ($row) return $row;
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function byTingkat(string $tingkat, string $lingkup, ?string $dapilLabel = null): array
    {
        $sql = 'SELECT p.*, per.status_aktif AS persona_aktif
                  FROM pejabat p
                  LEFT JOIN persona per ON per.id = p.persona_id
                 WHERE p.tingkat = ? AND p.lingkup = ?';
        $args = [$tingkat, $lingkup];
        if ($dapilLabel !== null) {
            $sql .= ' AND p.dapil_label = ?';
            $args[] = $dapilLabel;
        }
        $sql .= ' ORDER BY p.urutan ASC, p.id ASC';
        return Database::all($sql, $args);
    }
}
