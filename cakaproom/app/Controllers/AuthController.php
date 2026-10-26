<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function home(): string
    {
        $u = $this->user();
        if (!$u) return $this->redirect('/login');
        return $this->redirect(self::beranda($u['role']));
    }

    public static function beranda(string $role): string
    {
        return match ($role) {
            'admin' => '/admin',
            'dosen' => '/dosen',
            default => '/mhs',
        };
    }

    public function showLogin(): string
    {
        $u = $this->user();
        if ($u) return $this->redirect(self::beranda($u['role']));
        return $this->render('auth/login', ['title' => 'Masuk — CAKAPROOM']);
    }

    public function otpRequest(): string
    {
        $this->verifyCsrf();
        return $this->json((new AuthService())->request($this->str('email')));
    }

    public function otpVerify(): string
    {
        $this->verifyCsrf();
        $r = (new AuthService())->verify($this->str('email'), $this->str('kode'));
        if (!$r['ok']) {
            return $this->fail($r['error'], 422);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$r['user']['id'];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        $next = $r['user']['privasi_ok_at'] ? self::beranda($r['user']['role']) : '/privasi';
        return $this->ok(['next' => url($next), 'csrf' => $_SESSION['_csrf']]);
    }

    /** Pemberitahuan penggunaan data (UU PDP) pada login pertama. */
    public function privasi(): string
    {
        $u = $this->requireRole('admin', 'dosen', 'mahasiswa');
        return $this->render('auth/privasi', ['title' => 'Pemberitahuan Data', 'u' => $u]);
    }

    public function privasiOk(): string
    {
        $u = $this->requireRole('admin', 'dosen', 'mahasiswa');
        $this->verifyCsrf();
        Database::run('UPDATE cr_users SET privasi_ok_at = NOW() WHERE id = ?', [$u['id']]);
        return $this->redirect(self::beranda($u['role']));
    }

    public function logout(): string
    {
        $this->verifyCsrf();
        $_SESSION = [];
        session_regenerate_id(true);
        return $this->redirect('/login');
    }
}
