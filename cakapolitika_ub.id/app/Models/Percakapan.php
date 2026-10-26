<?php
namespace App\Models;

use App\Core\Database;

class Percakapan extends Model
{
    protected static string $table = 'percakapan';

    public static function findOrCreate(string $sessionId, ?int $wargaId, ?int $personaId, string $mode): array
    {
        $row = Database::one(
            'SELECT * FROM percakapan WHERE session_id = ? AND mode = ?'
            . ($personaId ? ' AND persona_id = ?' : ' AND persona_id IS NULL')
            . ' ORDER BY id DESC LIMIT 1',
            $personaId ? [$sessionId, $mode, $personaId] : [$sessionId, $mode]
        );
        if ($row) {
            return $row;
        }
        $id = self::create([
            'session_id' => $sessionId,
            'warga_id'   => $wargaId,
            'persona_id' => $personaId,
            'mode'       => $mode,
        ]);
        return self::find($id);
    }

    /** @return array<int,array<string,mixed>> chronological messages */
    public static function messages(int $percakapanId, int $limit = 40): array
    {
        return Database::all(
            'SELECT * FROM pesan WHERE percakapan_id = ? ORDER BY id ASC LIMIT ?',
            [$percakapanId, $limit]
        );
    }

    public static function addMessage(int $percakapanId, string $pengirim, string $isi, ?array $meta = null): int
    {
        return Database::insert('pesan', [
            'percakapan_id' => $percakapanId,
            'pengirim'      => $pengirim,
            'isi_pesan'     => $isi,
            'meta'          => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
