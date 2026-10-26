<?php
namespace App\Models;

use App\Core\Database;

/**
 * Administrative regions of Jawa Timur (kode Kemendagri, province "35").
 * level: 1=provinsi 2=kab/kota 3=kecamatan 4=kelurahan/desa
 */
class Wilayah extends Model
{
    protected static string $table = 'wilayah';

    public const PROVINSI_KODE = '35';

    /** @return array<int,array{kode:string,nama:string}> */
    public static function children(string $parentKode): array
    {
        return Database::all(
            'SELECT kode, nama FROM wilayah WHERE parent_kode = ? ORDER BY nama ASC',
            [$parentKode]
        );
    }

    public static function kabKota(): array
    {
        return self::children(self::PROVINSI_KODE);
    }

    public static function find(string|int $kode): ?array
    {
        return Database::one('SELECT * FROM wilayah WHERE kode = ?', [(string)$kode]);
    }

    public static function nama(string $kode): ?string
    {
        return Database::value('SELECT nama FROM wilayah WHERE kode = ?', [$kode]) ?: null;
    }

    /** Is this kode inside the given ancestor kode (or equal to it)? */
    public static function isDescendantOf(string $kode, string $ancestor): bool
    {
        return $kode === $ancestor || str_starts_with($kode, $ancestor . '.');
    }

    public static function available(): bool
    {
        try {
            return (int)Database::value('SELECT COUNT(*) FROM wilayah LIMIT 1') > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
