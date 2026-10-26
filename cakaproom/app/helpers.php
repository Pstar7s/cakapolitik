<?php

use App\Core\Bootstrap;
use App\Core\Router;

const PERAN = [
    'PEMERINTAH' => ['label' => 'Pemerintah', 'warna' => '#1f4e9c', 'ikon' => '🏛️'],
    'SIPIL'      => ['label' => 'Sipil / LSM', 'warna' => '#1a7f5a', 'ikon' => '🤝'],
    'SWASTA'     => ['label' => 'Swasta',      'warna' => '#b86e00', 'ikon' => '🏭'],
    'WARGA'      => ['label' => 'Warga',       'warna' => '#a43a6b', 'ikon' => '🏘️'],
];

const FASE_LABEL = [
    'lobby'      => 'Lobby',
    'argumen'    => 'Fase 1 · Argumen',
    'uji_silang' => 'Fase 2 · Uji Silang',
    'negosiasi'  => 'Fase 3 · Negosiasi & Joint Resolution',
    'penutupan'  => 'Fase 4 · Penutupan',
];

function config(?string $key = null, $default = null)
{
    return Bootstrap::config($key, $default);
}

/** URL aplikasi (menghormati subfolder; base path tidak di-hardcode). */
function url(string $path = '/'): string
{
    return Router::basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . config('app.asset_version', '1');
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function flash(?string $key = null, $value = null)
{
    if ($key === null) {
        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $all;
    }
    if ($value === null) {
        $v = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }
    $_SESSION['_flash'][$key] = $value;
    return null;
}

function is_local(): bool
{
    return config('app.env') === 'local';
}

function push_script(string $html): void
{
    $GLOBALS['__page_scripts'][] = $html;
}

function page_scripts(): string
{
    return implode("\n", $GLOBALS['__page_scripts'] ?? []);
}

function tgl($ts): string
{
    if (!$ts) return '-';
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y H:i', $t);
}

function peran_label(?string $kode): string
{
    return PERAN[$kode]['label'] ?? (string)$kode;
}

/** Hitung kata (Unicode-aware; angka & kata berimbuhan tanda hubung dihitung 1). */
function hitung_kata(string $s): int
{
    $s = trim($s);
    if ($s === '') return 0;
    preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $s, $m);
    return count($m[0]);
}

/** Teks buatan pengguna -> HTML aman dengan baris baru dipertahankan. */
function teks(?string $s): string
{
    return nl2br(e($s ?? ''), false);
}

function fmt_skor($n): string
{
    return $n === null ? '–' : rtrim(rtrim(number_format((float)$n, 2, ',', ''), '0'), ',');
}
