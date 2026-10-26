<?php
namespace App\Models;

use App\Core\Database;

class AdminUser extends Model
{
    protected static string $table = 'admin_user';

    public static function findByUsername(string $u): ?array
    {
        return Database::one('SELECT * FROM admin_user WHERE username = ?', [trim($u)]);
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE admin_user SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function setPassword(int $id, string $plain): void
    {
        self::updateById($id, ['password_hash' => password_hash($plain, PASSWORD_BCRYPT)]);
    }
}
