<?php
namespace App\Services;

use App\Core\Database;

/**
 * Pembungkus tipis Anthropic Messages API (cURL). Dipakai HANYA untuk skor
 * awal AI pasca-sesi. 'key' kosong -> $stub = true (pemanggil memakai
 * penilaian heuristik offline). Tiap panggilan dicatat ke cr_api_log.
 */
class ClaudeClient
{
    private array $cfg;
    public bool $stub;

    public function __construct()
    {
        $this->cfg  = config('claude');
        $this->stub = empty($this->cfg['key']);
    }

    public function model(): string
    {
        return $this->stub ? 'stub-heuristik' : $this->cfg['model'];
    }

    /** @return array{text:string,input_tokens:?int,output_tokens:?int} */
    public function chat(string $system, string $user, ?int $sesiId = null, string $konteks = 'skor_ai'): array
    {
        if ($this->stub) {
            throw new ClaudeException('Mode stub: tidak ada panggilan API.');
        }
        $t0 = microtime(true);
        $payload = [
            'model'      => $this->cfg['model'],
            'max_tokens' => $this->cfg['max_tokens'],
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ];
        $ch = curl_init(rtrim($this->cfg['base_url'], '/') . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg['timeout'] ?? 120,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->cfg['key'],
                'anthropic-version: ' . ($this->cfg['version'] ?? '2023-06-01'),
            ],
        ]);
        $raw  = curl_exec($ch);
        $err  = $raw === false ? curl_error($ch) : null;
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $body = is_string($raw) ? json_decode($raw, true) : null;
        $lat  = (int)((microtime(true) - $t0) * 1000);

        if ($err || $http >= 400 || !isset($body['content'])) {
            $msg = $err ?: ('HTTP ' . $http . ' ' . ($body['error']['message'] ?? substr((string)$raw, 0, 300)));
            Database::insert('cr_api_log', [
                'sesi_id' => $sesiId, 'konteks' => $konteks, 'model' => $this->cfg['model'], 'ok' => 0,
                'latency_ms' => $lat, 'error' => mb_substr($msg, 0, 500),
            ]);
            throw new ClaudeException($msg);
        }
        $text = '';
        foreach ($body['content'] as $b) {
            if (($b['type'] ?? '') === 'text') $text .= $b['text'];
        }
        $in  = $body['usage']['input_tokens'] ?? null;
        $out = $body['usage']['output_tokens'] ?? null;
        Database::insert('cr_api_log', [
            'sesi_id' => $sesiId, 'konteks' => $konteks, 'model' => $this->cfg['model'], 'ok' => 1,
            'input_tokens' => $in, 'output_tokens' => $out, 'latency_ms' => $lat,
        ]);
        return ['text' => trim($text), 'input_tokens' => $in, 'output_tokens' => $out];
    }
}
