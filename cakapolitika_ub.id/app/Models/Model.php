<?php
namespace App\Models;

use App\Core\Database;

/**
 * Minimal active-ish record. Subclasses set $table.
 */
abstract class Model
{
    protected static string $table = '';

    public static function table(): string
    {
        return static::$table;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `' . static::$table . '` WHERE id = ?', [$id]);
    }

    public static function all(string $orderBy = 'id ASC'): array
    {
        return Database::all('SELECT * FROM `' . static::$table . '` ORDER BY ' . $orderBy);
    }

    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return Database::update(static::$table, $data, 'id = :id', ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return Database::run('DELETE FROM `' . static::$table . '` WHERE id = ?', [$id])->rowCount();
    }

    public static function count(string $where = '1', array $args = []): int
    {
        return (int)Database::value('SELECT COUNT(*) FROM `' . static::$table . '` WHERE ' . $where, $args);
    }
}
