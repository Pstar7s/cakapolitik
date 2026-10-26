<?php
namespace App\Services;

use App\Models\Otp;
use App\Models\Warga;

class OtpService
{
    private array $cfg;

    public function __construct(private Mailer $mailer = new Mailer())
    {
        $this->cfg = config('otp');
    }

    /**
     * @return array{ok:bool, error?:string, dev_code?:string, resend_in?:int}
     */
    public function request(int $wargaId, string $email): array
    {
        $last = Otp::latestForWarga($wargaId);
        if ($last) {
            $age = time() - strtotime($last['created_at']);
            $wait = ($this->cfg['resend_seconds'] ?? 60) - $age;
            if ($wait > 0) {
                return ['ok' => false, 'error' => "Mohon tunggu {$wait} detik sebelum meminta kode baru.", 'resend_in' => $wait];
            }
        }
        if (Otp::countLastHour($wargaId) >= ($this->cfg['max_per_hour'] ?? 6)) {
            return ['ok' => false, 'error' => 'Terlalu banyak permintaan kode. Coba lagi dalam 1 jam.'];
        }

        $code = str_pad((string)random_int(0, (10 ** ($this->cfg['length'] ?? 6)) - 1), $this->cfg['length'] ?? 6, '0', STR_PAD_LEFT);
        Otp::issue($wargaId, $code, $this->cfg['expire_minutes'] ?? 10);

        $body = "Kode verifikasi CAKAPOLITIK_UB.ID Anda: {$code}\n\n"
            . "Berlaku {$this->cfg['expire_minutes']} menit. Jangan bagikan kode ini kepada siapa pun.";
        $this->mailer->send($email, 'Kode Verifikasi CAKAPOLITIK_UB.ID', $body);

        $out = ['ok' => true, 'resend_in' => $this->cfg['resend_seconds'] ?? 60];
        if (is_local()) {
            $out['dev_code'] = $code; // shown in UI only in local env
        }
        return $out;
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public function verify(int $wargaId, string $input): array
    {
        $otp = Otp::latestForWarga($wargaId);
        if (!$otp) {
            return ['ok' => false, 'error' => 'Belum ada kode. Minta kode terlebih dahulu.'];
        }
        if ((int)$otp['is_verified'] === 1) {
            return ['ok' => true];
        }
        if (strtotime($otp['expired_at']) < time()) {
            return ['ok' => false, 'error' => 'Kode sudah kedaluwarsa. Minta kode baru.'];
        }
        if ((int)$otp['attempts'] >= 5) {
            return ['ok' => false, 'error' => 'Terlalu banyak percobaan salah. Minta kode baru.'];
        }

        Otp::bumpAttempt((int)$otp['id']);
        if (!hash_equals($otp['kode_otp'], trim($input))) {
            return ['ok' => false, 'error' => 'Kode salah. Coba lagi.'];
        }

        Otp::markVerified((int)$otp['id']);
        Warga::markVerified($wargaId);
        return ['ok' => true];
    }
}
