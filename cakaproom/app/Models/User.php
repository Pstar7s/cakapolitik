<?php
namespace App\Models;

use App\Core\Database;

class User extends Model
{
    protected static string $table = 'cr_users';

    public static function byEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM cr_users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Buat atau perbarui (berdasarkan email). Mengembalikan [id, dibuatBaru]. */
    public static function upsert(string $role, string $nama, string $email, ?string $nim): array
    {
        $email = mb_strtolower(trim($email));
        $row = self::byEmail($email);
        if ($row) {
            // Peran tidak diturunkan/dinaikkan lewat impor CSV.
            Database::update('cr_users', ['nama' => $nama, 'nim' => $nim ?: $row['nim']], 'id = :id', ['id' => $row['id']]);
            return [(int)$row['id'], false];
        }
        $id = Database::insert('cr_users', [
            'role' => $role, 'nama' => $nama, 'email' => $email, 'nim' => $nim ?: null,
        ]);
        return [$id, true];
    }
}
