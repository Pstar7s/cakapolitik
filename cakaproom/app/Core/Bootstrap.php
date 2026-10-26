<?php
namespace App\Core;

/**
 * App bootstrap: PSR-4-ish autoloader, config, error handling, session.
 */
class Bootstrap
{
    private static array $config = [];

    public static function init(string $configFile): array
    {
        // --- Autoloader (App\  ->  app/) -----------------------------------
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }
            $rel  = substr($class, strlen($prefix));
            $file = ROOT_PATH . '/app/' . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });

        // --- Config ------------------------------------------------------------
        self::$config = require $configFile;
        $cfg = self::$config;

        date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Jakarta');

        // --- Error handling --------------------------------------------------
        $debug = (bool)($cfg['app']['debug'] ?? false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', ROOT_PATH . '/storage/logs/php-error.log');

        set_exception_handler([self::class, 'handleException']);

        require ROOT_PATH . '/app/helpers.php';

        // Composer autoloader (PHPMailer, dll) bila tersedia.
        $vendor = ROOT_PATH . '/vendor/autoload.php';
        if (is_file($vendor)) {
            require $vendor;
        }

        // --- Session -------------------------------------------------------
        $s = $cfg['session'];
        if (!empty($s['save_path']) && is_dir($s['save_path'])) {
            session_save_path($s['save_path']);
        }
        session_name($s['name'] ?? 'app_sess');
        session_set_cookie_params([
            'lifetime' => $s['lifetime'] ?? 7200,
            'path'     => Router::basePath() ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);
        session_start();

        return $cfg;
    }

    public static function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }
        $node = self::$config;
        foreach (explode('.', $key) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                return $default;
            }
            $node = $node[$seg];
        }
        return $node;
    }

    public static function handleException(\Throwable $e): void
    {
        error_log('[uncaught] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);

        $wantsJson = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            $out = ['ok' => false, 'error' => 'Terjadi kesalahan pada server.'];
            if (self::config('app.debug')) {
                $out['debug'] = $e->getMessage();
                $out['trace'] = explode("\n", $e->getTraceAsString());
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            return;
        }

        if (self::config('app.debug')) {
            echo '<h1>Application error</h1><pre>'
                . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString())
                . '</pre>';
        } else {
            echo '<h1>500 — Terjadi kesalahan</h1><p>Mohon coba beberapa saat lagi.</p>';
        }
    }
}
