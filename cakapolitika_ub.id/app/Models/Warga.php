<?php
namespace App\Models;

use App\Core\Database;

class Warga extends Model
{
    protected static string $table = 'warga';

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM warga WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Create or update the warga record for this email; returns the row. */
    public static function upsert(array $data): array
    {
        $data['email'] = mb_strtolower(trim($data['email']));
        $existing = self::findByEmail($data['email']);
        if ($existing) {
            unset($data['email']);
            if ($data) {
                self::updateById((int)$existing['id'], $data);
            }
            return self::find((int)$existing['id']);
        }
        $id = self::create($data);
        return self::find($id);
    }

    public static function markVerified(int $id): void
    {
        self::updateById($id, ['is_verified' => 1]);
    }

    public static function displayName(array $warga): string
    {
        return $warga['nama'] ?: 'Warga';
    }
}
