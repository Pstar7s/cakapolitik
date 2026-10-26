<?php

use App\Core\Bootstrap;
use App\Core\Router;

function config(?string $key = null, $default = null)
{
    return Bootstrap::config($key, $default);
}

/** Absolute URL for an app path, honouring the install sub-directory. */
function url(string $path = '/'): string
{
    $base = Router::basePath();
    return $base . '/' . ltrim($path, '/');
}

/** URL for a file under /assets. */
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

function old(string $key, $default = '')
{
    return e($_SESSION['_old'][$key] ?? $default);
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

/** Collect <script> / extra markup from a view for the layout to emit. */
function push_script(string $html): void
{
    $GLOBALS['__page_scripts'][] = $html;
}

function page_scripts(): string
{
    return implode("\n", $GLOBALS['__page_scripts'] ?? []);
}

/** Short human date. */
function tgl($ts): string
{
    if (!$ts) return '-';
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y H:i', $t);
}
