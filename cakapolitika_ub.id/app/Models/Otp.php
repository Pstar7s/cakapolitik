<?php
namespace App\Models;

use App\Core\Database;

class Otp extends Model
{
    protected static string $table = 'otp_verifikasi';

    public static function latestForWarga(int $wargaId): ?array
    {
        return Database::one(
            'SELECT * FROM otp_verifikasi WHERE warga_id = ? ORDER BY id DESC LIMIT 1',
            [$wargaId]
        );
    }

    public static function countLastHour(int $wargaId): int
    {
        return (int)Database::value(
            'SELECT COUNT(*) FROM otp_verifikasi
              WHERE warga_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)',
            [$wargaId]
        );
    }

    public static function issue(int $wargaId, string $code, int $expireMinutes): int
    {
        return self::create([
            'warga_id'   => $wargaId,
            'kode_otp'   => $code,
            'expired_at' => date('Y-m-d H:i:s', time() + $expireMinutes * 60),
        ]);
    }

    public static function bumpAttempt(int $id): void
    {
        Database::run('UPDATE otp_verifikasi SET attempts = attempts + 1 WHERE id = ?', [$id]);
    }

    public static function markVerified(int $id): void
    {
        Database::run('UPDATE otp_verifikasi SET is_verified = 1 WHERE id = ?', [$id]);
    }
}
