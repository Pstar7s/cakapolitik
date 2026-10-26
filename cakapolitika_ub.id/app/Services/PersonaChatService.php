<?php
namespace App\Services;

use App\Models\Aspirasi;
use App\Models\Percakapan;
use App\Models\Persona;

/**
 * Persona AI conversation: RAG retrieval + strict persona system prompt +
 * Claude call. Also records each substantive warga turn as an aspiration
 * (moderated + classified) — Technical Brief §3.1.
 */
class PersonaChatService
{
    public function __construct(
        private RagService $rag = new RagService(),
        private ClaudeClient $claude = new ClaudeClient(),
        private ModerationService $moderation = new ModerationService(),
        private AspirationClassifier $classifier = new AspirationClassifier(),
    ) {}

    /**
     * @return array{reply:string, persona:array, aspirasi:?array, retrieval:array, stub:bool}
     */
    public function handle(int $wargaId, int $personaId, string $sessionId, string $message): array
    {
        $persona = Persona::withDapil($personaId);
        if (!$persona || (int)$persona['status_aktif'] !== 1) {
            throw new \RuntimeException('Persona tidak tersedia.');
        }

        $pc = Percakapan::findOrCreate($sessionId, $wargaId, $personaId, 'persona');
        $history = Percakapan::messages((int)$pc['id']);

        // 1. Retrieve grounding context
        $retrieval = $this->rag->retrieve($personaId, $message);

        // 2. Persist the warga message
        Percakapan::addMessage((int)$pc['id'], 'warga', $message);

        // 3. Build prompt + call Claude
        $system = $this->buildSystemPrompt($persona, $retrieval['context']);
        $messages = $this->historyToMessages($history);
        $messages[] = ['role' => 'user', 'content' => $message];

        $res = $this->claude->chat($messages, $system, [
            'konteks'    => 'persona',
            'max_tokens' => 900,
        ]);
        $reply = $res['text'] !== '' ? $res['text']
            : 'Maaf, saya belum bisa menjawab saat ini. Silakan coba lagi.';

        Percakapan::addMessage((int)$pc['id'], 'ai', $reply, [
            'chunks' => $retrieval['chunks'],
            'usage'  => $res['usage'] ?? [],
        ]);

        // 4. Record aspiration (skip very short / greeting-only turns)
        $aspirasi = null;
        if ($this->looksLikeAspiration($message)) {
            $aspirasi = $this->recordAspiration($wargaId, $personaId, (int)$pc['id'], $message);
        }

        return [
            'reply'     => $reply,
            'persona'   => $persona,
            'aspirasi'  => $aspirasi,
            'retrieval' => $retrieval['chunks'],
            'stub'      => $res['stub'] ?? false,
        ];
    }

    private function recordAspiration(int $wargaId, int $personaId, int $pcId, string $text): array
    {
        $mod = $this->moderation->check($text);
        $kategori = $mod['status'] === 'lolos'
            ? $this->classifier->classify($text)
            : KategoriGuess::fromText($text);

        $isAnonim = !empty($_SESSION['flow']['anonim']);

        $id = Aspirasi::create([
            'warga_id'        => $wargaId,
            'persona_id'      => $personaId,
            'percakapan_id'   => $pcId,
            'isi_aspirasi'    => $text,
            'kategori'        => $kategori,
            'is_anonim'       => $isAnonim ? 1 : 0,
            'status_moderasi' => $mod['status'],
            'alasan_moderasi' => $mod['alasan'],
        ]);

        return [
            'id'       => $id,
            'kategori' => $kategori,
            'status'   => $mod['status'],
            'alasan'   => $mod['alasan'],
        ];
    }

    private function buildSystemPrompt(array $persona, string $context): string
    {
        $ctx = $context !== ''
            ? $context
            : 'Belum ada data knowledge base untuk persona ini. Sampaikan dengan jujur bahwa datamu masih terbatas.';

        $extra = trim((string)($persona['system_prompt_extra'] ?? ''));
        $gaya  = trim((string)($persona['gaya_bicara'] ?? 'Sopan, jelas, dan membumi.'));

        return <<<SYS
        Kamu adalah **{$persona['nama']}**, {$persona['jabatan']} dari partai {$persona['partai']},
        mewakili Daerah Pemilihan {$persona['nama_dapil']}.
        Deskripsi: {$persona['deskripsi_singkat']}

        GAYA BICARA: {$gaya}

        ATURAN WAJIB:
        1. Jawab HANYA berdasarkan KONTEKS di bawah. Dilarang mengarang fakta,
           angka, janji, atau pernyataan yang tidak ada di konteks.
        2. Jika pertanyaan warga di luar cakupan konteks, katakan dengan jujur
           bahwa kamu belum memiliki informasi tersebut. Jangan menebak.
        3. Terima aspirasi/keluhan warga dengan empati dan konfirmasikan bahwa
           aspirasi itu dicatat untuk ditindaklanjuti.
        4. Jangan keluar dari peran sebagai {$persona['nama']}. Jangan menyebut
           dirimu sebagai model bahasa; kamu adalah representasi AI dari tokoh ini.
        5. Bahasa Indonesia, ringkas (maksimal ~200 kata), tanpa markdown heading.
        {$extra}

        KONTEKS (knowledge base — satu-satunya sumber faktamu):
        ---
        {$ctx}
        ---
        Catatan untukmu: jawabanmu akan diberi label "Jawaban dihasilkan AI berdasarkan data publik" di UI.
        SYS;
    }

    private function historyToMessages(array $history): array
    {
        $out = [];
        foreach ($history as $m) {
            if ($m['pengirim'] === 'warga') {
                $out[] = ['role' => 'user', 'content' => $m['isi_pesan']];
            } elseif ($m['pengirim'] === 'ai') {
                $out[] = ['role' => 'assistant', 'content' => $m['isi_pesan']];
            }
        }
        // Claude API requires the first turn to be 'user'. Drop any leading
        // assistant turns (e.g. the scripted persona opening message).
        while ($out && $out[0]['role'] === 'assistant') {
            array_shift($out);
        }
        return $out;
    }

    private function looksLikeAspiration(string $text): bool
    {
        $t = trim($text);
        if (mb_strlen($t) < 12) {
            return false;
        }
        foreach (['halo', 'hai', 'assalamualaikum', 'terima kasih', 'terimakasih', 'oke', 'baik pak', 'sip'] as $greet) {
            if (mb_strtolower($t) === $greet) {
                return false;
            }
        }
        return true;
    }
}
