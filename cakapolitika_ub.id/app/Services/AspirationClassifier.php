<?php
namespace App\Services;

use App\Models\Aspirasi;

/**
 * Classifies an aspiration into one of the 8 categories + 'lainnya'
 * (Technical Brief §3.1 step 8). LLM first, keyword fallback.
 */
class AspirationClassifier
{
    public function __construct(private ClaudeClient $claude = new ClaudeClient()) {}

    public function classify(string $text): string
    {
        $kategori = implode(', ', Aspirasi::KATEGORI);
        $system = "Klasifikasikan aspirasi/keluhan warga ke SATU kategori dari daftar: {$kategori}. "
            . "Jawab HANYA satu kata kategori, tanpa tanda baca atau penjelasan.";

        try {
            $res = $this->claude->chat(
                [['role' => 'user', 'content' => $text]],
                $system,
                ['konteks' => 'klasifikasi', 'model' => config('claude.model_fast'), 'max_tokens' => 12, 'temperature' => 0]
            );
            $norm = KategoriGuess::normalize($res['text']);
            if ($norm !== 'lainnya') {
                return $norm;
            }
        } catch (ClaudeException $e) {
            error_log('[klasifikasi] fallback: ' . $e->getMessage());
        }

        return KategoriGuess::fromText($text);
    }
}
