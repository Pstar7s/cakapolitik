<?php
namespace App\Models;

use App\Core\Database;

class ApiLog extends Model
{
    protected static string $table = 'api_log';

    public static function record(array $data): void
    {
        try {
            self::create($data);
        } catch (\Throwable $e) {
            error_log('[api_log] ' . $e->getMessage());
        }
    }

    /** Aggregate stats for the admin cost dashboard. */
    public static function stats(): array
    {
        $row = Database::one(
            'SELECT COUNT(*) calls,
                    COALESCE(SUM(input_tokens),0) in_tok,
                    COALESCE(SUM(output_tokens),0) out_tok,
                    COALESCE(SUM(ok=0),0) errors,
                    COALESCE(AVG(latency_ms),0) avg_ms
               FROM api_log'
        ) ?: [];
        $byCtx = Database::all(
            'SELECT konteks,
                    COUNT(*) calls,
                    COALESCE(SUM(input_tokens),0) in_tok,
                    COALESCE(SUM(output_tokens),0) out_tok
               FROM api_log GROUP BY konteks ORDER BY calls DESC'
        );
        return ['overall' => $row, 'by_context' => $byCtx];
    }
}
