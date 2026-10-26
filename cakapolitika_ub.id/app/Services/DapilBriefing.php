<?php
namespace App\Services;

use App\Models\Pejabat;
use App\Models\Wilayah;

/**
 * Merakit "briefing dapil" untuk BRONE: dari alamat warga -> daftar wakil
 * di tiga tingkat (DPR RI, DPRD Provinsi, DPRD kota/kabupaten) plus kepala
 * daerah. Perhatikan bahwa nomor dapil BERBEDA di tiap tingkat untuk alamat
 * yang sama (mis. Blimbing = DPR RI Jatim 5, DPRD Prov Dapil 6, DPRD Kota
 * Dapil 2), jadi pemetaan dilakukan lewat tabel `wilayah_dapil`.
 */
class DapilBriefing
{
    /** @return array<string,mixed>|null null bila alamat di luar cakupan data */
    public static function forWarga(array $warga): ?array
    {
        if (!Pejabat::available()) {
            return null;
        }

        [$kabKode, $kecKode] = self::kodeDari($warga);
        $peta = Pejabat::petaDapil(
            $kabKode,
            $kecKode,
            $warga['kab_kota'] ?? null,
            $warga['kecamatan'] ?? null
        );
        if (!$peta) {
            return null;
        }

        $kabNama = $peta['kab_kota_nama'];
        $isKabupaten = str_starts_with(mb_strtolower($kabNama), 'kabupaten');

        // Kepala daerah
        $eksekutif = array_merge(
            Pejabat::byTingkat('gubernur', 'Jawa Timur'),
            Pejabat::byTingkat('wagub', 'Jawa Timur'),
            $isKabupaten
                ? array_merge(Pejabat::byTingkat('bupati', $kabNama), Pejabat::byTingkat('wabup', $kabNama))
                : array_merge(Pejabat::byTingkat('walikota', $kabNama), Pejabat::byTingkat('wawalikota', $kabNama))
        );

        $dprRi   = Pejabat::byTingkat('dpr_ri', 'Jawa Timur', $peta['dapil_dpr_ri']);
        $dprdProv = Pejabat::byTingkat('dprd_prov', 'Jawa Timur', $peta['dapil_dprd_prov']);
        $dprdLokal = [];
        if (!empty($peta['lembaga_dprd_lokal']) && !empty($peta['dapil_dprd_lokal'])) {
            $dprdLokal = Pejabat::byTingkat('dprd_lokal', $peta['lembaga_dprd_lokal'], $peta['dapil_dprd_lokal']);
        }

        $adaVerifikasi = false;
        foreach ($eksekutif as $e) {
            if ((int)($e['perlu_verifikasi'] ?? 0) === 1) {
                $adaVerifikasi = true;
                break;
            }
        }

        return [
            'kab_kota'          => $kabNama,
            'kecamatan'         => $peta['kecamatan_nama'] ?: ($warga['kecamatan'] ?? ''),
            'kelurahan'         => $warga['kelurahan'] ?? '',
            'dapil_dpr_ri'      => $peta['dapil_dpr_ri'],
            'cakupan_dpr_ri'    => $peta['cakupan_dpr_ri'],
            'dapil_dprd_prov'   => $peta['dapil_dprd_prov'],
            'cakupan_dprd_prov' => $peta['cakupan_dprd_prov'],
            'lembaga_dprd_lokal'=> $peta['lembaga_dprd_lokal'],
            'dapil_dprd_lokal'  => $peta['dapil_dprd_lokal'],
            'cakupan_dprd_lokal'=> $peta['cakupan_dprd_lokal'],
            'eksekutif'         => $eksekutif,
            'dpr_ri'            => $dprRi,
            'dprd_prov'         => $dprdProv,
            'dprd_lokal'        => $dprdLokal,
            'ada_verifikasi'    => $adaVerifikasi,
        ];
    }

    /** @return array{0:?string,1:?string} [kabKode, kecKode] */
    private static function kodeDari(array $warga): array
    {
        $kode = preg_replace('/[^0-9.]/', '', (string)($warga['kode_wilayah'] ?? ''));
        if ($kode && substr_count($kode, '.') >= 2) {
            $seg = explode('.', $kode);
            return ["$seg[0].$seg[1]", "$seg[0].$seg[1].$seg[2]"];
        }
        // Coba temukan kode dari nama bila data wilayah tersedia.
        if (!empty($warga['kab_kota']) && Wilayah::available()) {
            foreach (Wilayah::kabKota() as $kab) {
                if (mb_strtolower($kab['nama']) === mb_strtolower($warga['kab_kota'])) {
                    $kecKode = null;
                    if (!empty($warga['kecamatan'])) {
                        foreach (Wilayah::children($kab['kode']) as $kec) {
                            if (mb_strtolower($kec['nama']) === mb_strtolower($warga['kecamatan'])) {
                                $kecKode = $kec['kode'];
                                break;
                            }
                        }
                    }
                    return [$kab['kode'], $kecKode];
                }
            }
        }
        return [null, null];
    }

    /**
     * Format satu baris pejabat: "Nama (Partai)" atau "Nama" bila tak berpartai.
     * Menandai persona yang aktif dengan ✅.
     */
    public static function baris(array $p): string
    {
        $s = $p['nama'];
        if (!empty($p['partai'])) {
            $s .= ' (' . $p['partai'] . ')';
        }
        if (!empty($p['persona_id']) && (int)($p['persona_aktif'] ?? 0) === 1) {
            $s .= ' ✅';
        }
        return $s;
    }
}
