<?php
namespace App\Services;

use App\Models\KnowledgeChunk;

/**
 * Fase 1 retrieval: lexical TF-IDF cosine similarity computed in PHP.
 * No external embedding service (Technical Brief §4.1 — RAG on shared hosting).
 * The `embedding` column is reserved for a Fase 2 vector upgrade.
 */
class RagService
{
    private array $cfg;

    // Very small Indonesian stopword list — enough to sharpen matching.
    private const STOP = [
        'yang','di','ke','dari','dan','atau','untuk','pada','dengan','ini','itu',
        'adalah','akan','tidak','ada','saya','kami','kita','anda','pak','bu','bapak',
        'ibu','apa','bagaimana','kenapa','mengapa','kapan','mohon','tolong','ya',
        'sudah','juga','agar','supaya','karena','sebagai','oleh','dalam','para','se',
    ];

    public function __construct()
    {
        $this->cfg = config('rag');
    }

    /**
     * @return array{context:string, chunks:array<int,array<string,mixed>>}
     */
    public function retrieve(int $personaId, string $query): array
    {
        $chunks = KnowledgeChunk::forPersona($personaId, true);
        if (!$chunks) {
            return ['context' => '', 'chunks' => []];
        }

        $docTokens = [];
        $df = [];
        foreach ($chunks as $i => $c) {
            $toks = $this->tokenize($c['teks_chunk'] . ' ' . ($c['judul'] ?? ''));
            $docTokens[$i] = $toks;
            foreach (array_unique(array_keys($toks)) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        $N = count($chunks);
        $idf = [];
        foreach ($df as $term => $d) {
            $idf[$term] = log(($N + 1) / ($d + 0.5)) + 1;
        }

        $qTokens = $this->tokenize($query);
        if (!$qTokens) {
            return ['context' => '', 'chunks' => []];
        }
        $qVec = $this->tfidfVector($qTokens, $idf);

        $scored = [];
        foreach ($docTokens as $i => $toks) {
            $dVec = $this->tfidfVector($toks, $idf);
            $score = $this->cosine($qVec, $dVec);
            if ($score >= ($this->cfg['min_score'] ?? 0.02)) {
                $scored[] = ['chunk' => $chunks[$i], 'score' => round($score, 4)];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, $this->cfg['top_k'] ?? 4);

        // Fallback: if lexical matching found nothing, use the first few chunks
        // so the persona still has some grounding rather than none.
        if (!$top) {
            foreach (array_slice($chunks, 0, 3) as $c) {
                $top[] = ['chunk' => $c, 'score' => 0.0];
            }
        }

        return [
            'context' => $this->buildContext($top),
            'chunks'  => array_map(fn($s) => [
                'id'     => (int)$s['chunk']['id'],
                'judul'  => $s['chunk']['judul'],
                'sumber' => $s['chunk']['sumber'],
                'score'  => $s['score'],
            ], $top),
        ];
    }

    private function buildContext(array $top): string
    {
        $parts = [];
        $budget = $this->cfg['max_context_ch'] ?? 6000;
        foreach ($top as $n => $s) {
            $c = $s['chunk'];
            $head = '[' . ($n + 1) . '] '
                . ($c['judul'] ? $c['judul'] . ' — ' : '')
                . 'sumber: ' . ($c['sumber'] ?: 'tidak diketahui')
                . ($c['referensi'] ? ' (' . $c['referensi'] . ')' : '');
            $block = $head . "\n" . trim($c['teks_chunk']);
            if (mb_strlen(implode("\n\n", $parts) . "\n\n" . $block) > $budget) {
                break;
            }
            $parts[] = $block;
        }
        return implode("\n\n", $parts);
    }

    /** @return array<string,int> term => count */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $out = [];
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $w) {
            if ($w === '' || mb_strlen($w) < 3 || in_array($w, self::STOP, true)) {
                continue;
            }
            $out[$w] = ($out[$w] ?? 0) + 1;
        }
        return $out;
    }

    private function tfidfVector(array $tokens, array $idf): array
    {
        $vec = [];
        $max = max($tokens ?: [1]);
        foreach ($tokens as $term => $tf) {
            $w = (0.5 + 0.5 * $tf / $max) * ($idf[$term] ?? 1.0);
            $vec[$term] = $w;
        }
        return $vec;
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        foreach ($a as $k => $v) {
            if (isset($b[$k])) $dot += $v * $b[$k];
        }
        $na = sqrt(array_sum(array_map(fn($v) => $v * $v, $a)));
        $nb = sqrt(array_sum(array_map(fn($v) => $v * $v, $b)));
        return ($na && $nb) ? $dot / ($na * $nb) : 0.0;
    }
}
