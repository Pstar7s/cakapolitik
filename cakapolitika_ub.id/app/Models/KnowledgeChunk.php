<?php
namespace App\Models;

use App\Core\Database;

class KnowledgeChunk extends Model
{
    protected static string $table = 'persona_knowledge_chunks';

    /** @return array<int,array<string,mixed>> */
    public static function forPersona(int $personaId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM persona_knowledge_chunks WHERE persona_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_aktif = 1';
        }
        $sql .= ' ORDER BY id ASC';
        return Database::all($sql, [$personaId]);
    }

    public static function countForPersona(int $personaId): int
    {
        return (int)Database::value(
            'SELECT COUNT(*) FROM persona_knowledge_chunks WHERE persona_id = ?',
            [$personaId]
        );
    }
}
