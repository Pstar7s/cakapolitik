<?php
namespace App\Services;

use App\Models\Persona;

/**
 * "BRONE" — the host chatbot. Per Technical Brief §6.1 BRONE only navigates
 * (welcome, collect identity, show persona choices, hand off). It needs no RAG.
 * Fase 1 keeps BRONE scripted (deterministic copy) to keep API cost at zero
 * for navigation turns; the persona AI is where Claude is actually spent.
 */
class BroneService
{
    public static function greeting(): string
    {
        return "Assalamualaikum, salam hormat! Saya **BRONE**, teman ngobrol Anda di CAKAPOLITIK_UB.ID. "
            . "Saya akan membantu menghubungkan aspirasi Anda dengan pemimpin politik di daerah pemilihan Anda.\n\n"
            . "Untuk mulai, mohon lengkapi data diri Anda dulu ya (sesuai KTP).";
    }

    public static function otpSent(string $email): string
    {
        return "Terima kasih. Saya sudah mengirim **kode verifikasi 6 digit** ke {$email}. "
            . "Silakan cek email Anda (termasuk folder spam) lalu masukkan kodenya di bawah.";
    }

    public static function verified(array $warga, array $dapil): string
    {
        $nama = $warga['nama'] ?? 'Warga';
        $brief = DapilBriefing::forWarga($warga);

        if (!$brief) {
            $wil = $dapil['wilayah_cakupan'] ?? '';
            return "Verifikasi berhasil, terima kasih {$nama}! ✅\n\n"
                . "Berdasarkan alamat Anda, Anda berada di **Dapil {$dapil['nama_dapil']}** ({$wil}). "
                . "Berikut pemimpin politik dari dapil Anda yang sudah bergabung di program ini:";
        }

        return self::briefingText($nama, $brief);
    }

    /** Rakit sapaan lengkap BRONE dari hasil DapilBriefing. */
    private static function briefingText(string $nama, array $b): string
    {
        $L = "Verifikasi berhasil, terima kasih {$nama}! ✅\n\n";

        $lokasi = trim(($b['kelurahan'] ? "Kel./Desa {$b['kelurahan']}, " : '')
            . ($b['kecamatan'] ? "Kec. {$b['kecamatan']}, " : '')
            . $b['kab_kota'], ', ');
        $L .= "Alamat Anda ({$lokasi}) masuk ke:\n";
        $L .= "• **DPR RI — Dapil {$b['dapil_dpr_ri']}** ({$b['cakupan_dpr_ri']})\n";
        $L .= "• **DPRD Provinsi Jatim — Dapil {$b['dapil_dprd_prov']}** ({$b['cakupan_dprd_prov']})\n";
        if ($b['dprd_lokal']) {
            $L .= "• **{$b['lembaga_dprd_lokal']} — Dapil {$b['dapil_dprd_lokal']}** ({$b['cakupan_dprd_lokal']})\n";
        } elseif (!empty($b['lembaga_dprd_lokal'])) {
            $L .= "• **{$b['lembaga_dprd_lokal']}** (rincian dapil per kecamatan menyusul)\n";
        }

        if ($b['eksekutif']) {
            $L .= "\n**Kepala daerah Anda:**\n";
            foreach ($b['eksekutif'] as $e) {
                $L .= "• {$e['jabatan']}: {$e['nama']}\n";
            }
        }

        $L .= self::daftar('Anggota DPR RI (Dapil ' . $b['dapil_dpr_ri'] . ')', $b['dpr_ri']);
        $L .= self::daftar('Anggota DPRD Provinsi Jatim (Dapil ' . $b['dapil_dprd_prov'] . ')', $b['dprd_prov']);
        if ($b['dprd_lokal']) {
            $L .= self::daftar($b['lembaga_dprd_lokal'] . ' (Dapil ' . $b['dapil_dprd_lokal'] . ')', $b['dprd_lokal']);
        }

        if ($b['ada_verifikasi']) {
            $L .= "\n_Nama kepala daerah masih menunggu verifikasi tim._\n";
        }

        $L .= "\nDari daftar di atas, tanda ✅ berarti tokoh tersebut sudah bergabung "
            . "di CAKAPOLITIK_UB.ID dan bisa Anda ajak berdialog sekarang. "
            . "Mau dihubungkan dengan siapa? Silakan pilih di bawah.";

        return $L;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function daftar(string $judul, array $rows): string
    {
        if (!$rows) {
            return "\n**{$judul}:**\n_(data menyusul)_\n";
        }
        $out = "\n**{$judul}:**\n";
        $i = 1;
        foreach ($rows as $r) {
            $out .= $i++ . '. ' . DapilBriefing::baris($r) . "\n";
        }
        return $out;
    }

    public static function outsideDapil(): string
    {
        return "Mohon maaf, untuk tahap purwarupa ini CAKAPOLITIK_UB.ID baru melayani **Dapil Jawa Timur V "
            . "(Malang Raya: Kabupaten Malang, Kota Malang, Kota Batu)**.\n\n"
            . "Alamat yang Anda masukkan sepertinya di luar wilayah tersebut. Anda tetap bisa menjelajah demo, "
            . "namun aspirasi dari luar dapil demo belum kami teruskan.";
    }

    /** @return array<int,array<string,mixed>> persona cards */
    public static function personaCards(int $dapilId): array
    {
        $out = [];
        foreach (Persona::activeByDapil($dapilId) as $p) {
            $out[] = [
                'id'       => (int)$p['id'],
                'nama'     => $p['nama'],
                'partai'   => $p['partai'],
                'jabatan'  => $p['jabatan'],
                'foto_url' => $p['foto_url'],
                'deskripsi'=> $p['deskripsi_singkat'],
            ];
        }
        return $out;
    }

    public static function handoff(array $persona): string
    {
        return "Baik, saya sambungkan Anda dengan **{$persona['nama']}** ({$persona['partai']} — {$persona['jabatan']}).\n\n"
            . "Silakan sampaikan pertanyaan atau aspirasi Anda. Setiap aspirasi akan otomatis dicatat "
            . "dan dikategorikan untuk ditindaklanjuti. _Jawaban persona dihasilkan AI berdasarkan data publik._";
    }

    public static function personaInactive(): string
    {
        return "Mohon maaf, tokoh tersebut belum bergabung dalam program CAKAPOLITIK_UB.ID. "
            . "Silakan pilih pemimpin politik lain yang tersedia.";
    }

    public static function backToMenu(): string
    {
        return "Anda kembali ke menu. Silakan pilih pemimpin politik lain yang ingin Anda ajak bicara, "
            . "atau akhiri sesi jika sudah cukup.";
    }

    public static function closing(): string
    {
        return "Terima kasih sudah berkunjung ke CAKAPOLITIK_UB.ID! Saya BRONE, senang menemani Anda hari ini. "
            . "Aspirasi Anda sudah tercatat. Mari kita wujudkan ruang publik digital yang lebih cerdas dan bermanfaat. "
            . "Sampai jumpa! 👋";
    }

    /** Ajakan mengisi jajak pendapat sebelum sesi ditutup. */
    public static function pollIntro(array $warga): string
    {
        $nama = trim((string)($warga['nama'] ?? '')) ?: 'Warga';
        return "Baik {$nama}, sebelum kita akhiri — boleh minta waktu sebentar? 🙏\n\n"
            . "Silakan pilih **satu prioritas utama** untuk tiap bidang di bawah ini. "
            . "Jawaban Anda akan diringkas menjadi grafik aspirasi warga Malang Raya di Dashboard. "
            . "Bidang yang tidak ingin Anda jawab boleh dilewati.";
    }

    /** Penutup setelah warga mengisi / melewati jajak pendapat. */
    public static function pollThanks(bool $skipped): string
    {
        $lead = $skipped
            ? "Tidak masalah, jajak pendapat kita lewati. "
            : "Terima kasih! Pilihan aspirasi Anda sudah tercatat dan akan tampil dalam grafik di Dashboard. ";
        return $lead . "\n\n" . self::closing();
    }
}
