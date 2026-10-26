<?php
namespace App\Services;

/**
 * One-layer automatic moderation (Technical Brief §6.4). Flags hate speech /
 * SARA / abusive language / hoax indication. No human review queue in Fase 1.
 */
class ModerationService
{
    public function __construct(private ClaudeClient $claude = new ClaudeClient()) {}

    /**
     * @return array{status:'lolos'|'ditahan', alasan:string}
     */
    public function check(string $text): array
    {
        $system = <<<SYS
        Kamu adalah moderator konten untuk platform aspirasi warga di Indonesia.
        Nilai apakah teks warga mengandung SALAH SATU dari: ujaran kebencian,
        SARA (menyerang suku/agama/ras/antargolongan), kata kasar/makian yang
        ditujukan ke orang, ancaman kekerasan, atau indikasi hoaks yang jelas.

        Kritik keras terhadap kebijakan/pejabat TETAP DIPERBOLEHKAN selama tidak
        memakai makian kasar, tidak menyerang identitas SARA, dan tidak mengancam.

        Jawab HANYA dengan JSON valid satu baris:
        {"aman": true|false, "alasan": "penjelasan singkat 1 kalimat"}
        SYS;

        try {
            $res = $this->claude->chat(
                [['role' => 'user', 'content' => $text]],
                $system,
                ['konteks' => 'moderasi', 'model' => config('claude.model_fast'), 'max_tokens' => 200, 'temperature' => 0]
            );
            $json = $this->extractJson($res['text']);
            if (is_array($json) && array_key_exists('aman', $json)) {
                return $json['aman']
                    ? ['status' => 'lolos', 'alasan' => (string)($json['alasan'] ?? 'Tidak ada indikasi pelanggaran.')]
                    : ['status' => 'ditahan', 'alasan' => (string)($json['alasan'] ?? 'Terindikasi melanggar pedoman konten.')];
            }
        } catch (ClaudeException $e) {
            error_log('[moderasi] fallback: ' . $e->getMessage());
        }

        // Fallback heuristic if the API is unavailable.
        return $this->heuristic($text);
    }

    private function heuristic(string $text): array
    {
        $t = ' ' . mb_strtolower($text) . ' ';
        $bad = ['anjing','bangsat','goblok','tolol','kontol','memek','bajingan','kafir','bunuh','babi ','pki','cebong','kampret'];
        foreach ($bad as $w) {
            if (str_contains($t, $w)) {
                return ['status' => 'ditahan', 'alasan' => 'Terdeteksi kata kasar/berpotensi ujaran kebencian (deteksi cadangan).'];
            }
        }
        return ['status' => 'lolos', 'alasan' => 'Tidak ada indikasi pelanggaran (deteksi cadangan).'];
    }

    private function extractJson(string $s): ?array
    {
        if (preg_match('/\{.*\}/s', $s, $m)) {
            $d = json_decode($m[0], true);
            return is_array($d) ? $d : null;
        }
        return null;
    }
}
