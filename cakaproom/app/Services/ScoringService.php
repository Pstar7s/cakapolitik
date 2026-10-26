<?php
namespace App\Services;

use App\Core\Database;

/**
 * Skor awal AI per KELOMPOK (brief bagian 8). AI hanya memberi SARAN.
 * - Nama diganti kode anonim (Anggota-N) sebelum dikirim ke API.
 * - Skor total dihitung di PHP (0,35A + 0,35B + 0,30C), bukan oleh LLM.
 * - JSON mentah, model, versi prompt disimpan; hitung ulang menambah riwayat.
 */
class ScoringService
{
    public const VERSI_PROMPT = 'v1-2026-10';
    public const BOBOT = ['A' => 0.35, 'B' => 0.35, 'C' => 0.30];

    public static function total(float $a, float $b, float $c): float
    {
        return round(self::BOBOT['A'] * $a + self::BOBOT['B'] * $b + self::BOBOT['C'] * $c, 2);
    }

    /** @return array<string,array> hasil per peran */
    public static function hitung(array $sesi): array
    {
        if (!in_array($sesi['status'], ['selesai', 'dinilai'], true)) {
            throw new \DomainException('Skor AI hanya dapat dihitung setelah sesi diakhiri.');
        }
        $kasus = Database::one('SELECT * FROM cr_kasus WHERE id = ?', [$sesi['kasus_id']]);
        $client = new ClaudeClient();
        $hasil = [];
        foreach (SesiService::KODE_PERAN as $peran) {
            $paket = self::paket($sesi, $peran);
            $meta = ['model' => $client->model(), 'in' => null, 'out' => null];
            $nilai = null;
            $raw = null;

            if (!$client->stub && $paket['statistik']['argumen'] + $paket['statistik']['pesan_negosiasi'] > 0) {
                try {
                    $res = $client->chat(self::systemPrompt($kasus), self::userPrompt($kasus, $peran, $paket), (int)$sesi['id']);
                    $nilai = self::parse($res['text']);
                    $raw = $res['text'];
                    $meta['in'] = $res['input_tokens']; $meta['out'] = $res['output_tokens'];
                } catch (ClaudeException $e) {
                    // Gagal API: jatuh ke heuristik dan tandai di rasional (dosen tahu ini bukan LLM).
                    $meta['model'] = 'stub-heuristik (API gagal)';
                    $raw = 'API error: ' . $e->getMessage();
                }
            }
            if (!$nilai) {
                $nilai = self::heuristik($paket);
                $meta['model'] = $meta['model'] === $client->model() && !$client->stub ? 'stub-heuristik (data kosong)' : $meta['model'];
            }

            $a = self::klem($nilai['A']['skor']); $b = self::klem($nilai['B']['skor']); $c = self::klem($nilai['C']['skor']);
            $total = self::total($a, $b, $c);
            $id = Database::insert('cr_skor_ai', [
                'sesi_id' => (int)$sesi['id'], 'kode_peran' => $peran,
                'model' => $meta['model'], 'versi_prompt' => self::VERSI_PROMPT,
                'skor_analisis' => $a, 'skor_negosiasi' => $b, 'skor_nilai' => $c,
                'skor_total_ai' => $total, 'bukti_terbatas' => $nilai['bukti_terbatas'] ? 1 : 0,
                'rasional_json' => json_encode($nilai, JSON_UNESCAPED_UNICODE),
                'raw_json' => $raw, 'input_tokens' => $meta['in'], 'output_tokens' => $meta['out'],
            ]);
            $hasil[$peran] = Database::one('SELECT * FROM cr_skor_ai WHERE id = ?', [$id]);
        }
        return $hasil;
    }

    /** Skor AI terbaru per peran. */
    public static function terbaru(int $sesiId): array
    {
        $out = [];
        foreach (Database::all(
            'SELECT s.* FROM cr_skor_ai s JOIN (SELECT kode_peran, MAX(id) mid FROM cr_skor_ai WHERE sesi_id = ? GROUP BY kode_peran) m ON m.mid = s.id', [$sesiId]) as $r) {
            $r['detail'] = json_decode($r['rasional_json'] ?? '', true) ?: [];
            $out[$r['kode_peran']] = $r;
        }
        return $out;
    }

    // --- Paket data (anonim) -------------------------------------------------------

    public static function paket(array $sesi, string $peran): array
    {
        $id = (int)$sesi['id'];
        $anggota = Database::all('SELECT user_id FROM cr_peserta_sesi WHERE sesi_id = ? AND kode_peran = ? ORDER BY user_id', [$id, $peran]);
        $kode = [];
        foreach ($anggota as $i => $a) $kode[(int)$a['user_id']] = 'Anggota-' . ($i + 1);
        $anon = fn($uid) => $kode[(int)$uid] ?? 'Peserta-peran-lain';

        $arg = array_map(fn($p) => [
            'id' => (int)$p['id'], 'oleh' => $anon($p['user_id']),
            'claim' => $p['claim'], 'evidence' => $p['evidence'], 'nilai_kebangsaan' => $p['nilai_kebangsaan'],
        ], Database::all("SELECT * FROM cr_post WHERE sesi_id = ? AND kode_peran = ? AND tipe = 'argumen' ORDER BY id", [$id, $peran]));

        // Tanya-jawab uji silang yang melibatkan kelompok ini.
        $qa = [];
        $qs = Database::all("SELECT * FROM cr_post WHERE sesi_id = ? AND tipe = 'pertanyaan' AND (kode_peran = ? OR target_peran = ?) ORDER BY id", [$id, $peran, $peran]);
        foreach ($qs as $q) {
            $jw = Database::all("SELECT * FROM cr_post WHERE sesi_id = ? AND tipe = 'jawaban' AND parent_id = ? ORDER BY id", [$id, $q['id']]);
            $qa[] = [
                'id' => (int)$q['id'], 'arah' => $q['kode_peran'] === $peran ? 'diajukan kelompok ini ke ' . $q['target_peran'] : 'diajukan ' . $q['kode_peran'] . ' kepada kelompok ini',
                'penanya' => $q['kode_peran'] === $peran ? $anon($q['user_id']) : $q['kode_peran'],
                'pertanyaan' => $q['isi'],
                'jawaban' => array_map(fn($j) => [
                    'id' => (int)$j['id'], 'oleh' => $j['kode_peran'] === $peran ? $anon($j['user_id']) : $j['kode_peran'], 'isi' => $j['isi'],
                ], $jw),
            ];
        }
        $neg = array_map(fn($p) => ['id' => (int)$p['id'], 'oleh' => $anon($p['user_id']), 'isi' => $p['isi']],
            Database::all("SELECT * FROM cr_post WHERE sesi_id = ? AND kode_peran = ? AND tipe = 'negosiasi' ORDER BY id", [$id, $peran]));

        $res = Database::value('SELECT teks_final FROM cr_resolusi WHERE sesi_id = ?', [$id]) ?: '';
        $sikap = ResolusiService::sikap($id)[$peran] ?? null;

        $dijawab = 0; $diajukan = 0;
        foreach ($qa as $q) {
            if (str_starts_with($q['arah'], 'diajukan kelompok')) { $diajukan++; } else { if ($q['jawaban']) $dijawab++; }
        }
        $stat = [
            'anggota' => count($anggota), 'argumen' => count($arg), 'pertanyaan_diajukan' => $diajukan,
            'pertanyaan_diterima_dijawab' => $dijawab,
            'pesan_negosiasi' => count($neg), 'sikap' => $sikap['sikap'] ?? null,
        ];
        return ['peran' => $peran, 'argumen' => $arg, 'uji_silang' => $qa, 'negosiasi' => $neg,
                'joint_resolution' => $res, 'sikap_kelompok' => $sikap ? ['sikap' => $sikap['sikap'], 'catatan' => $sikap['catatan']] : null,
                'statistik' => $stat];
    }

    // --- Prompt --------------------------------------------------------------------

    public static function systemPrompt(array $kasus): string
    {
        $rubrik = $kasus['rubrik_json'] ?: '(rubrik belum diisi)';
        $status = $kasus['rubrik_status'] === 'disetujui' ? 'DISETUJUI dosen' : 'SEMENTARA (draf, belum disetujui dosen)';
        return <<<TXT
Anda membantu dosen memberi SARAN skor awal untuk simulasi debat kebijakan berbasis peran (CAKAPROOM). Keputusan nilai ada pada dosen.

ATURAN WAJIB
1. Nilai KUALITAS PENALARAN DALAM PERAN yang ditugaskan, bukan sikap politik/ideologi atau pandangan pribadi mahasiswa.
2. "Nilai Kebangsaan" dinilai dari kaitan yang EKSPLISIT dan BERALASAN dengan Pancasila/konstitusi serta etika berargumen, bukan dari orientasi politik.
3. Keempat peran dinilai dengan standar yang sama; tidak ada peran yang otomatis lebih benar.
4. Jangan menilai ejaan/tata bahasa kecuali menghambat makna, dan jangan menilai pihak mana yang dianggap benar.
5. Setiap skor WAJIB disertai rujukan ID postingan sebagai bukti (bukti: [id,...]). Jika bukti kurang, isi "bukti_terbatas": true dan jangan menebak.
6. Nama peserta sudah dianonimkan; jangan mencoba mengidentifikasi orang.
7. Skor total TIDAK Anda hitung; sistem menghitungnya.

RUBRIK (status: {$status}) — A = Kedalaman Analisis Data/Hukum (35%), B = Negosiasi & Empati Peran (35%), C = Nilai Kebangsaan & Etika Politik (30%):
{$rubrik}

KELUARAN: hanya satu objek JSON valid, tanpa teks lain, dengan bentuk persis:
{"A":{"skor":0-100,"rasional":"<=80 kata","bukti":[id,id]},"B":{...},"C":{...},"bukti_terbatas":false}
TXT;
    }

    public static function userPrompt(array $kasus, string $peran, array $paket): string
    {
        $konteks = "KASUS: {$kasus['judul']}\n\nLATAR BELAKANG:\n" . mb_substr((string)$kasus['latar_belakang'], 0, 4000)
                 . "\n\nDATA REFERENSI:\n" . mb_substr((string)$kasus['referensi_data'], 0, 4000);
        return $konteks . "\n\nKELOMPOK YANG DINILAI: " . $peran . "\n\nTRANSKRIP (JSON):\n"
             . json_encode($paket, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Ekstrak & validasi JSON dari keluaran model. */
    public static function parse(string $text): ?array
    {
        if (!preg_match('/\{.*\}/s', $text, $m)) return null;
        $j = json_decode($m[0], true);
        if (!is_array($j)) return null;
        $out = ['bukti_terbatas' => !empty($j['bukti_terbatas'])];
        foreach (['A', 'B', 'C'] as $k) {
            if (!isset($j[$k]['skor']) || !is_numeric($j[$k]['skor'])) return null;
            $out[$k] = [
                'skor'     => self::klem((float)$j[$k]['skor']),
                'rasional' => mb_substr(trim((string)($j[$k]['rasional'] ?? '')), 0, 700),
                'bukti'    => array_values(array_filter(array_map('intval', (array)($j[$k]['bukti'] ?? [])))),
            ];
        }
        return $out;
    }

    private static function klem($v): float
    {
        return round(max(0, min(100, (float)$v)), 2);
    }

    // --- Mode stub: heuristik deterministik (BUKAN penilaian nyata) ------------------

    public static function heuristik(array $p): array
    {
        $arg = $p['argumen'];
        $n = count($arg);
        $teks = '';
        foreach ($arg as $a) $teks .= ' ' . $a['claim'] . ' ' . $a['evidence'] . ' ' . $a['nilai_kebangsaan'];
        $angka = preg_match_all('/\d[\d.,]*/u', $teks);
        $hukum = preg_match_all('/\b(pasal|uu|uud|undang-undang|peraturan)\b/iu', $teks);
        $sila  = preg_match_all('/\bsila\b|pancasila|keadilan sosial|kemanusiaan|permusyawaratan|persatuan/iu', $teks);

        $perArg = $n ? 1 / $n : 0;
        $a = 40 + min(25, $angka * $perArg * 4) + min(20, $hukum * $perArg * 10);
        $negoAktif = $p['statistik']['pesan_negosiasi'] + 2 * ($p['statistik']['pertanyaan_diajukan'] + $p['statistik']['pertanyaan_diterima_dijawab']);
        $b = 38 + min(30, $negoAktif * 2.5) + ($p['sikap_kelompok'] ? 10 : 0)
           + (mb_strlen($p['joint_resolution']) > 400 ? 8 : 0);
        $c = 40 + min(35, $sila * $perArg * 12) + ($p['statistik']['argumen'] ? 8 : 0);
        $kurang = $n < 3 || ($p['statistik']['pesan_negosiasi'] + $p['statistik']['pertanyaan_diajukan']) < 2;
        $ids = array_slice(array_column($arg, 'id'), 0, 3);

        $cat = '[Skor stub heuristik — bukan penilaian LLM] ';
        return [
            'A' => ['skor' => self::klem($a), 'rasional' => $cat . "Terdeteksi {$angka} angka/data dan {$hukum} rujukan hukum pada {$n} argumen.", 'bukti' => $ids],
            'B' => ['skor' => self::klem($b), 'rasional' => $cat . "Aktivitas negosiasi/uji silang: {$negoAktif} poin; sikap peran " . ($p['sikap_kelompok']['sikap'] ?? 'belum dicatat') . '.', 'bukti' => array_slice(array_column($p['negosiasi'], 'id'), 0, 3)],
            'C' => ['skor' => self::klem($c), 'rasional' => $cat . "Terdeteksi {$sila} rujukan nilai/sila pada {$n} argumen.", 'bukti' => $ids],
            'bukti_terbatas' => $kurang,
        ];
    }
}
