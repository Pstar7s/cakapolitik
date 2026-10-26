<?php
namespace App\Services;

use App\Core\Database;
use App\Models\User;

/**
 * Login email + OTP. Akun dibuat admin/dosen (impor CSV); tidak ada pendaftaran
 * mandiri. Rate limit: jeda kirim ulang, maks per jam, maks percobaan salah.
 */
class AuthService
{
    private array $cfg;

    public function __construct(private Mailer $mailer = new Mailer())
    {
        $this->cfg = config('otp');
    }

    /** @return array{ok:bool,error?:string,resend_in?:int,dev_code?:string} */
    public function request(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Format email tidak valid.'];
        }
        $user = User::byEmail($email);
        $generic = ['ok' => true, 'resend_in' => $this->cfg['resend_seconds']];

        // Jangan membocorkan apakah email terdaftar.
        if (!$user || (int)$user['is_active'] !== 1) {
            return $generic;
        }

        $uid  = (int)$user['id'];
        $last = Database::one('SELECT created_at FROM cr_otp WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
        if ($last) {
            $wait = $this->cfg['resend_seconds'] - (time() - strtotime($last['created_at']));
            if ($wait > 0) {
                return ['ok' => false, 'error' => "Mohon tunggu {$wait} detik sebelum meminta kode baru.", 'resend_in' => $wait];
            }
        }
        $n = (int)Database::value(
            'SELECT COUNT(*) FROM cr_otp WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$uid]
        );
        if ($n >= $this->cfg['max_per_hour']) {
            return ['ok' => false, 'error' => 'Terlalu banyak permintaan kode. Coba lagi dalam 1 jam.'];
        }

        $len  = (int)$this->cfg['length'];
        $code = str_pad((string)random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT);
        Database::insert('cr_otp', [
            'user_id'    => $uid,
            'kode_otp'   => $code,
            'expired_at' => date('Y-m-d H:i:s', time() + $this->cfg['expire_minutes'] * 60),
        ]);
        $this->mailer->send($email, 'Kode Masuk CAKAPROOM',
            "Kode masuk CAKAPROOM Anda: {$code}\n\nBerlaku {$this->cfg['expire_minutes']} menit. Jangan bagikan kode ini kepada siapa pun.");

        if (is_local()) {
            $generic['dev_code'] = $code;
        }
        return $generic;
    }

    /** @return array{ok:bool,error?:string,user?:array} */
    public function verify(string $email, string $input): array
    {
        $user = User::byEmail($email);
        $bad  = ['ok' => false, 'error' => 'Kode salah atau sudah kedaluwarsa.'];
        if (!$user || (int)$user['is_active'] !== 1) {
            return $bad;
        }
        $otp = Database::one('SELECT * FROM cr_otp WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int)$user['id']]);
        if (!$otp || (int)$otp['is_used'] === 1 || strtotime($otp['expired_at']) < time()) {
            return $bad;
        }
        if ((int)$otp['attempts'] >= $this->cfg['max_attempts']) {
            return ['ok' => false, 'error' => 'Terlalu banyak percobaan salah. Minta kode baru.'];
        }
        Database::run('UPDATE cr_otp SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
        if (!hash_equals($otp['kode_otp'], trim($input))) {
            return ['ok' => false, 'error' => 'Kode salah. Coba lagi.'];
        }
        Database::run('UPDATE cr_otp SET is_used = 1 WHERE id = ?', [$otp['id']]);
        Database::run('UPDATE cr_users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        return ['ok' => true, 'user' => $user];
    }
}
