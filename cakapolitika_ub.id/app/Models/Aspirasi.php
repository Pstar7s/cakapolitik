<?php
namespace App\Models;

use App\Core\Database;

class Aspirasi extends Model
{
    protected static string $table = 'aspirasi';

    public const KATEGORI = [
        'pendidikan', 'kesehatan', 'ekonomi', 'infrastruktur',
        'pemerintahan', 'hukum', 'lingkungan', 'sosial-budaya', 'lainnya',
    ];

    /** Counts per kategori for aspirasi that passed moderation. */
    public static function summaryByKategori(): array
    {
        $rows = Database::all(
            "SELECT kategori, COUNT(*) c
               FROM aspirasi
              WHERE status_moderasi = 'lolos'
              GROUP BY kategori"
        );
        $out = array_fill_keys(self::KATEGORI, 0);
        foreach ($rows as $r) {
            $out[$r['kategori']] = (int)$r['c'];
        }
        return $out;
    }

    public static function summaryByPersona(): array
    {
        return Database::all(
            "SELECT p.nama, p.partai, COUNT(a.id) c
               FROM aspirasi a
               JOIN persona p ON p.id = a.persona_id
              WHERE a.status_moderasi = 'lolos'
              GROUP BY a.persona_id
              ORDER BY c DESC"
        );
    }

    /** @return array{rows:array,total:int} */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $args = [];
        if (!empty($filters['kategori'])) {
            $where[] = 'a.kategori = ?';
            $args[] = $filters['kategori'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status_moderasi = ?';
            $args[] = $filters['status'];
        }
        if (!empty($filters['persona_id'])) {
            $where[] = 'a.persona_id = ?';
            $args[] = (int)$filters['persona_id'];
        }
        $w = implode(' AND ', $where);
        $total = (int)Database::value("SELECT COUNT(*) FROM aspirasi a WHERE $w", $args);

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::all(
            "SELECT a.*, w.nama AS warga_nama, w.email AS warga_email,
                    w.kelurahan, w.kecamatan, w.kab_kota, p.nama AS persona_nama
               FROM aspirasi a
               JOIN warga w ON w.id = a.warga_id
               LEFT JOIN persona p ON p.id = a.persona_id
              WHERE $w
              ORDER BY a.id DESC
              LIMIT $perPage OFFSET $offset",
            $args
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public static function recentPublic(int $limit = 8): array
    {
        return Database::all(
            "SELECT a.isi_aspirasi, a.kategori, a.is_anonim, a.created_at,
                    p.nama AS persona_nama,
                    CASE WHEN a.is_anonim = 1 THEN NULL ELSE w.nama END AS warga_nama,
                    w.kecamatan, w.kab_kota
               FROM aspirasi a
               JOIN warga w ON w.id = a.warga_id
               LEFT JOIN persona p ON p.id = a.persona_id
              WHERE a.status_moderasi = 'lolos'
              ORDER BY a.id DESC
              LIMIT " . (int)$limit
        );
    }
}
