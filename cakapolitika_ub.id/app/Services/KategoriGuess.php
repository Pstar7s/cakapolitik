<?php
namespace App\Services;

use App\Models\Aspirasi;

/**
 * Lightweight keyword classifier for the 8 aspiration categories.
 * Used as the STUB classifier and as a fallback when the LLM answer is unclear.
 */
class KategoriGuess
{
    private const MAP = [
        'pendidikan'    => ['sekolah', 'guru', 'siswa', 'kuliah', 'kampus', 'beasiswa', 'kurikulum', 'pendidikan', 'literasi', 'ppdb'],
        'kesehatan'     => ['rumah sakit', 'puskesmas', 'dokter', 'bpjs', 'obat', 'gizi', 'stunting', 'kesehatan', 'posyandu', 'imunisasi'],
        'ekonomi'       => ['umkm', 'usaha', 'lapangan kerja', 'pengangguran', 'harga', 'sembako', 'modal', 'ekonomi', 'petani', 'nelayan', 'pasar', 'investasi'],
        'infrastruktur' => ['jalan', 'jembatan', 'drainase', 'banjir', 'lampu', 'penerangan', 'air bersih', 'internet', 'sinyal', 'transportasi', 'angkot', 'trotoar'],
        'pemerintahan'  => ['korupsi', 'transparansi', 'anggaran', 'apbn', 'apbd', 'pelayanan publik', 'birokrasi', 'pungli', 'kebijakan', 'perizinan'],
        'hukum'         => ['hukum', 'polisi', 'pengadilan', 'keadilan', 'kriminal', 'begal', 'narkoba', 'ham', 'pidana', 'perkara'],
        'lingkungan'    => ['sampah', 'polusi', 'sungai', 'hutan', 'lingkungan', 'limbah', 'reboisasi', 'tpa', 'udara', 'iklim'],
        'sosial-budaya' => ['budaya', 'disabilitas', 'lansia', 'anak', 'perempuan', 'toleransi', 'adat', 'seni', 'sosial', 'bansos', 'difabel'],
    ];

    public static function fromText(string $text): string
    {
        $t = ' ' . mb_strtolower($text) . ' ';
        $best = 'lainnya';
        $bestHits = 0;
        foreach (self::MAP as $kategori => $words) {
            $hits = 0;
            foreach ($words as $w) {
                if (str_contains($t, $w)) $hits++;
            }
            if ($hits > $bestHits) {
                $bestHits = $hits;
                $best = $kategori;
            }
        }
        return $best;
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        $raw = trim($raw, " \t\n\r\0\x0B.\"'");
        return in_array($raw, Aspirasi::KATEGORI, true) ? $raw : 'lainnya';
    }
}
