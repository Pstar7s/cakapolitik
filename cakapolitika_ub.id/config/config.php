<?php
/**
 * Base configuration (safe to commit).
 */

$config = [

    'app' => [
        'name'      => 'CAKAPOLITIK_UB.ID',
        'tagline'   => 'Memperkuat Demokrasi di Ruang Digital',
        // 'env': 'local' | 'production'. In 'local', errors are shown and OTP
        // codes are surfaced in the API response for easy testing.
        'env'       => 'production',
        'debug'     => true,
        // Bump when JS/CSS changes so browsers pick up the new file.
        'asset_version' => '3',
        // Leave 'base_url' null to auto-detect (recommended for subfolder deploys).
        'base_url'  => null,
        'timezone'  => 'Asia/Jakarta',
    ],

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'ganti dengan nama database',
        'user'    => 'ganti dengan user database',
        'pass'    => 'ganti dengan password database',
        'charset' => 'utf8mb4',
    ],

    // Anthropic Claude API. Billing is held by the client — never hardcode the
    // real key here. Put it in config.local.php. When 'key' is empty the app
    // runs in STUB mode: deterministic canned replies so the full flow is
    // testable without spending credits.
    'claude' => [
        'key'         => '',
        'base_url'    => 'https://api.anthropic.com/v1',
        'version'     => '2023-06-01',
        // Main persona/BRONE chat model. 'claude-sonnet-5' is a deliberate
        // cost choice for a public high-traffic chatbot (billing held by client
        // — see Technical Brief §4.1/§8). Bump to 'claude-opus-5' for max quality.
        'model'       => 'claude-sonnet-5',
        // Cheap model for moderation + aspiration classification (short outputs).
        'model_fast'  => 'claude-haiku-4-5',
        'max_tokens'  => 1024,
        'timeout'     => 45,
    ],

    // RAG retrieval: lexical (TF-IDF cosine) — no external embedding service.
    'rag' => [
        'top_k'          => 4,
        'min_score'      => 0.02,
        'max_context_ch' => 6000,
    ],

    'otp' => [
        'length'          => 6,
        'expire_minutes'  => 10,
        'resend_seconds'  => 60,
        'max_per_hour'    => 6,
    ],

    // OTP email delivery. 'driver': 'log' (writes to storage/logs, no send) or
    // 'smtp' (PHPMailer). In 'local' env the code is also returned in the API
    // response regardless of driver.
    // NOTE: real SMTP host/user/password belong in config.local.php, not here.
    // --- Pengiriman email OTP (SMTP) --------------------------------------
    'mail' => [
        'driver'    => 'smtp',
        'from'      => 'ganti dengan nama email, contoh : xxx@ub.id',
        'from_name' => 'CAKAPOLITIK_UB.ID',
        'smtp' => [
            'host'   => 'ganti dengan host server, contoh : mail.ub.id',
            'port'   => 587,
            'user'   => 'ganti dengan nama email, contoh : xxx@ub.id',
            'pass'   => 'ganti dengan password email',
            'secure' => 'tls',
        ],
    ],

    'session' => [
        'name'          => 'cakapolitik_ub_sess',
        'lifetime'      => 7200,
        'save_path'     => null, // set below to storage/sessions
    ],
];

$config['session']['save_path'] = dirname(__DIR__) . '/storage/sessions';

return $config;
