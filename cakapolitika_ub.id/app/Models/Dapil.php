<?php
namespace App\Models;

use App\Core\Database;

class Dapil extends Model
{
    protected static string $table = 'dapil';

    /** The single hard-coded demo dapil for Fase 1. */
    public static function demo(): ?array
    {
        return Database::one('SELECT * FROM dapil ORDER BY id ASC LIMIT 1');
    }

    /** Kode kab/kota (level 2) yang termasuk dapil demo — Malang Raya. */
    public const DEMO_KAB_KOTA = ['35.07', '35.73', '35.79']; // Kab. Malang, Kota Malang, Kota Batu

    /**
     * Does an address fall inside the demo dapil (Malang Raya)?
     * Prefer the wilayah kode (exact); fall back to name matching.
     */
    public static function matchesDemo(string $kabKota, ?string $kodeWilayah = null): bool
    {
        if ($kodeWilayah) {
            foreach (self::DEMO_KAB_KOTA as $kab) {
                if ($kodeWilayah === $kab || str_starts_with($kodeWilayah, $kab . '.')) {
                    return true;
                }
            }
            return false;
        }
        $n = mb_strtolower(trim($kabKota));
        foreach (['malang', 'kota batu'] as $needle) {
            if (str_contains($n, $needle)) {
                return true;
            }
        }
        return false;
    }
}
