<?php
namespace App\Models;

use App\Core\Database;

class Persona extends Model
{
    protected static string $table = 'persona';

    /** @return array<int,array<string,mixed>> */
    public static function activeByDapil(int $dapilId): array
    {
        return Database::all(
            'SELECT p.*, d.nama_dapil
               FROM persona p
               LEFT JOIN dapil d ON d.id = p.dapil_id
              WHERE p.status_aktif = 1 AND p.dapil_id = ?
              ORDER BY p.nama ASC',
            [$dapilId]
        );
    }

    public static function withDapil(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, d.nama_dapil, d.wilayah_cakupan
               FROM persona p
               LEFT JOIN dapil d ON d.id = p.dapil_id
              WHERE p.id = ?',
            [$id]
        );
    }

    public static function isActive(int $id): bool
    {
        return (int)Database::value('SELECT status_aktif FROM persona WHERE id = ?', [$id]) === 1;
    }
}
