<?php
namespace App\Services;

use App\Models\ApiLog;

/**
 * Thin wrapper around the Anthropic Messages API (raw HTTP via cURL, per
 * Technical Brief §4.1). When no API key is configured it runs in STUB mode
 * so the whole flow is testable without spending credits.
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

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{text:string,usage:array,stop_reason:?string,stub:bool}
     */
    public function chat(array $messages, string $system, array $opts = []): array
    {
        $konteks = $opts['konteks'] ?? 'chat';
        $model   = $opts['model']   ?? $this->cfg['model'];
        $maxTok  = $opts['max_tokens'] ?? $this->cfg['max_tokens'];
        $t0 = microtime(true);

        if ($this->stub) {
            $res = $this->stubResponse($messages, $system, $konteks);
            ApiLog::record([
                'konteks' => $konteks, 'model' => 'stub', 'ok' => 1,
                'input_tokens' => 0, 'output_tokens' => 0,
                'latency_ms' => (int)((microtime(true) - $t0) * 1000),
            ]);
            return $res + ['stub' => true];
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTok,
            'system'     => $system,
            'messages'   => array_map(fn($m) => [
                'role'    => $m['role'],
                'content' => $m['content'],
            ], $messages),
        ];
        if (isset($opts['temperature'])) {
            $payload['temperature'] = $opts['temperature'];
        }

        [$http, $body, $err] = $this->httpPost('/messages', $payload);
        $latency = (int)((microtime(true) - $t0) * 1000);

        if ($err || $http >= 400 || !isset($body['content'])) {
            $msg = $err ?: ('HTTP ' . $http . ' ' . ($body['error']['message'] ?? substr(json_encode($body), 0, 300)));
            ApiLog::record([
                'konteks' => $konteks, 'model' => $model, 'ok' => 0,
                'error' => mb_substr($msg, 0, 500), 'latency_ms' => $latency,
            ]);
            throw new ClaudeException($msg);
        }

        $text = '';
        foreach ($body['content'] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $usage = $body['usage'] ?? [];
        ApiLog::record([
            'konteks' => $konteks, 'model' => $model, 'ok' => 1,
            'input_tokens'  => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'latency_ms'    => $latency,
        ]);

        return [
            'text'        => trim($text),
            'usage'       => $usage,
            'stop_reason' => $body['stop_reason'] ?? null,
            'stub'        => false,
        ];
    }

    // ---------------------------------------------------------------------

    private function httpPost(string $path, array $payload): array
    {
        $ch = curl_init(rtrim($this->cfg['base_url'], '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg['timeout'] ?? 45,
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
        return [$http, is_array($body) ? $body : [], $err];
    }

    /**
     * Deterministic offline responses. Good enough to exercise the UI, the
     * conversation store, moderation and classification wiring end-to-end.
     */
    private function stubResponse(array $messages, string $system, string $konteks): array
    {
        $last = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') { $last = $messages[$i]['content']; break; }
        }

        if ($konteks === 'moderasi') {
            $bad = $this->looksAbusive($last);
            $json = json_encode([
                'aman'   => !$bad,
                'alasan' => $bad ? 'Terdeteksi kata kasar / berpotensi ujaran kebencian (deteksi stub).' : 'Tidak ada indikasi pelanggaran.',
            ], JSON_UNESCAPED_UNICODE);
            return ['text' => $json, 'usage' => [], 'stop_reason' => 'end_turn'];
        }

        if ($konteks === 'klasifikasi') {
            return ['text' => KategoriGuess::fromText($last), 'usage' => [], 'stop_reason' => 'end_turn'];
        }

        // Persona / BRONE chat stub
        $ctxNote = str_contains($system, 'KONTEKS')
            ? 'Berdasarkan data publik yang tersedia, '
            : '';
        $reply = "[MODE DEMO — belum terhubung ke Claude API]\n\n"
            . $ctxNote . "terima kasih atas pertanyaan/aspirasi Anda: \""
            . mb_strimwidth(trim($last), 0, 160, '…')
            . "\".\n\nPada versi produksi, jawaban ini akan dihasilkan AI berdasarkan knowledge base persona. "
            . "Aspirasi Anda tetap tercatat untuk ditindaklanjuti.";
        return ['text' => $reply, 'usage' => [], 'stop_reason' => 'end_turn'];
    }

    private function looksAbusive(string $t): bool
    {
        $t = ' ' . mb_strtolower($t) . ' ';
        foreach (['anjing', 'bangsat', 'goblok', 'tolol', 'bodoh', 'kafir', 'bunuh', 'kontol', 'memek', 'babi ', 'setan'] as $w) {
            if (str_contains($t, $w)) return true;
        }
        return false;
    }
}
