<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\AdminUser;

class AdminAuthController extends Controller
{
    public function showLogin(): string
    {
        if ($this->currentAdmin()) {
            return $this->redirect('/admin');
        }
        return $this->render('admin/login', [
            'title' => 'Login Admin — CAKAPOLITIK_UB.ID',
            'error' => flash('error'),
        ], 'layouts/admin_bare');
    }

    public function login(): string
    {
        $this->verifyCsrf();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $admin = AdminUser::findByUsername($username);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            flash('error', 'Username atau password salah.');
            return $this->redirect('/admin/login');
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        AdminUser::touchLogin((int)$admin['id']);
        return $this->redirect('/admin');
    }

    public function logout(): string
    {
        $this->verifyCsrf();
        unset($_SESSION['admin_id']);
        return $this->redirect('/admin/login');
    }
}
