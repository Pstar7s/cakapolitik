<?php
namespace App\Core;

abstract class Controller
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();
    }

    // --- Response helpers ----------------------------------------------------

    protected function render(string $view, array $data = [], ?string $layout = 'layouts/public'): string
    {
        $this->view->render($view, $data, $layout);
        return '';
    }

    protected function json($data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    // --- Request helpers ---------------------------------------------------

    /** Parsed request body: JSON body if present, else POST. */
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

    // --- CSRF ------------------------------------------------------------

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', (string)$token)) {
            http_response_code(419);
            throw new \RuntimeException('Sesi kedaluwarsa atau token tidak valid. Muat ulang halaman.');
        }
    }

    // --- Guards --------------------------------------------------------

    protected function currentWarga(): ?array
    {
        $id = $_SESSION['warga_id'] ?? null;
        if (!$id) {
            return null;
        }
        return \App\Models\Warga::find((int)$id);
    }

    protected function requireWargaJson(): array
    {
        $w = $this->currentWarga();
        if (!$w || empty($_SESSION['warga_verified'])) {
            $this->fail('Verifikasi identitas diperlukan.', 401);
            exit;
        }
        return $w;
    }

    protected function currentAdmin(): ?array
    {
        $id = $_SESSION['admin_id'] ?? null;
        return $id ? \App\Models\AdminUser::find((int)$id) : null;
    }

    protected function requireAdmin(): array
    {
        $a = $this->currentAdmin();
        if (!$a) {
            header('Location: ' . url('/admin/login'));
            exit;
        }
        return $a;
    }
}
