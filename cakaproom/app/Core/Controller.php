<?php
namespace App\Core;

use App\Models\User;

abstract class Controller
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();
    }

    // --- Response ------------------------------------------------------------

    protected function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $this->view->render($view, $data, $layout);
        return '';
    }

    protected function json($data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return '';
    }

    protected function ok(array $data = []): string
    {
        return $this->json(['ok' => true] + $data);
    }

    protected function fail(string $message, int $status = 422, array $extra = []): string
    {
        return $this->json(['ok' => false, 'error' => $message] + $extra, $status);
    }

    protected function redirect(string $path): string
    {
        header('Location: ' . url($path));
        return '';
    }

    protected function csv(string $filename, array $rows): string
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        foreach ($rows as $r) {
            // Cegah CSV/formula injection dari teks buatan pengguna.
            $r = array_map(fn($c) => is_string($c) && $c !== '' && strpbrk($c[0], "=+-@\t\r") ? "'" . $c : $c, $r);
            fputcsv($out, $r);
        }
        fclose($out);
        return '';
    }

    // --- Request ---------------------------------------------------------------

    protected function body(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $raw = file_get_contents('php://input');
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($raw !== '' && str_contains($ctype, 'application/json')) {
            $decoded = json_decode($raw, true);
            return $cache = is_array($decoded) ? $decoded : [];
        }
        return $cache = $_POST;
    }

    protected function input(string $key, $default = null)
    {
        $b = $this->body();
        return array_key_exists($key, $b) ? $b[$key] : ($_GET[$key] ?? $default);
    }

    protected function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_string($v) ? trim($v) : $default;
    }

    protected function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int)$v : $default;
    }

    // --- CSRF -------------------------------------------------------------------

    /** Untuk form HTML & fetch(): token dari field _csrf atau header X-CSRF-Token. */
    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($this->body()['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', (string)$token)) {
            if ($this->wantsJson()) {
                $this->fail('Sesi kedaluwarsa. Muat ulang halaman.', 419);
            } else {
                http_response_code(419);
                echo 'Sesi kedaluwarsa atau token tidak valid. Muat ulang halaman.';
            }
            exit;
        }
    }

    protected function wantsJson(): bool
    {
        return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    // --- Guards (RBAC) --------------------------------------------------------

    protected function user(): ?array
    {
        static $u = false;
        if ($u !== false) {
            return $u;
        }
        $id = $_SESSION['uid'] ?? null;
        $row = $id ? User::find((int)$id) : null;
        return $u = ($row && (int)$row['is_active'] === 1) ? $row : null;
    }

    /**
     * Wajib login dengan salah satu peran. Halaman -> redirect ke /login;
     * API -> 401/403 JSON. Memanggil exit bila gagal.
     */
    protected function requireRole(string ...$roles): array
    {
        $u = $this->user();
        if (!$u) {
            if ($this->wantsJson()) {
                $this->fail('Silakan login terlebih dahulu.', 401);
            } else {
                header('Location: ' . url('/login'));
            }
            exit;
        }
        // Pemberitahuan penggunaan data wajib disetujui pada login pertama (UU PDP).
        if (!$u['privasi_ok_at'] && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/privasi')) {
            if ($this->wantsJson()) {
                $this->fail('Setujui pemberitahuan penggunaan data terlebih dahulu.', 403);
            } else {
                header('Location: ' . url('/privasi'));
            }
            exit;
        }
        if (!in_array($u['role'], $roles, true)) {
            if ($this->wantsJson()) {
                $this->fail('Anda tidak memiliki akses.', 403);
            } else {
                http_response_code(403);
                $this->view->render('errors/403', [], 'layouts/app');
            }
            exit;
        }
        return $u;
    }

    protected function audit(string $aksi, $detail = null): void
    {
        $u = $this->user();
        Database::insert('cr_audit_log', [
            'user_id' => $u['id'] ?? null,
            'aksi'    => $aksi,
            'detail'  => is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
