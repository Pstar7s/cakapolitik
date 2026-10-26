<?php
/**
 * Base configuration CAKAPROOM (aman di-commit).
 * Rahasia & override per-lingkungan -> config.local.php (git-ignored).
 */

$config = [

    'app' => [
        'name'      => 'CAKAPROOM',
        'tagline'   => 'Ruang Debat Digital Berbasis Kasus',
        // 'local': error tampil & kode OTP ikut dikembalikan di respons (untuk uji).
        'env'       => 'local',
        'debug'     => true,
        'asset_version' => '5',
        // null = deteksi otomatis (subfolder / path final A8 tidak di-hardcode).
        'base_url'  => null,
        'timezone'  => 'Asia/Jakarta',
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'cakaproom',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Claude API: dipakai HANYA untuk skor awal AI setelah sesi (batch).
    // 'key' kosong = mode STUB (skor heuristik deterministik, tanpa biaya).
    'claude' => [
        'key'        => '',
        'base_url'   => 'https://api.anthropic.com/v1',
        'version'    => '2023-06-01',
        'model'      => 'claude-sonnet-5',
        'max_tokens' => 2048,
        'timeout'    => 120,
    ],

    'otp' => [
        'length'         => 6,
        'expire_minutes' => 10,
        'resend_seconds' => 60,
        'max_per_hour'   => 6,
        'max_attempts'   => 5,
    ],

    'mail' => [
        'driver'    => 'log',     // 'log' | 'smtp'
        'from'      => 'noreply@cakaproom.local',
        'from_name' => 'CAKAPROOM',
        'smtp' => ['host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'secure' => 'tls'],
    ],

    // Default sesi (Asumsi A2/A3/A4/A6/A9 — semuanya configurable; dosen bisa
    // mengubah durasi & rentang kata per sesi).
    'sesi' => [
        'word_min'     => 100,
        'word_max'     => 200,
        // detik per fase (total 60 menit)
        'durasi' => [
            'lobby'      => 300,
            'argumen'    => 1200,
            'uji_silang' => 900,
            'negosiasi'  => 900,
            'penutupan'  => 300,
        ],
        'max_argumen_per_mhs' => 3,     // A4: wajib >=1, boleh lebih sampai batas ini
        'pertanyaan_max_kata' => 50,    // A9
        'jawaban_max_kata'    => 100,   // A9
        'negosiasi_max_char'  => 280,   // A6
        'delegasi_per_peran'  => 2,     // A5
        'delegasi_mode'       => 'acak',// 'acak' (default) | 'dosen' (dosen tunjuk manual)
        'klausul_lock_detik'  => 45,    // kunci per-klausul Joint Resolution
        'poll_ms'             => 2500,  // interval polling klien (adapter real-time: polling)
        'online_window_detik' => 12,    // dianggap online bila terakhir terlihat < ini
    ],

    // Penilaian: batas penyesuaian nilai individual (null = tanpa batas).
    'nilai' => [
        'batas_individu' => null,
    ],

    'session' => [
        'name'      => 'cakaproom_sess',
        'lifetime'  => 14400,
        'save_path' => null,
    ],
];

$config['session']['save_path'] = dirname(__DIR__) . '/storage/sessions';

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return $config;
