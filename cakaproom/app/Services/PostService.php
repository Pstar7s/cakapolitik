<?php
namespace App\Services;

use App\Core\Database;

/**
 * Posting argumen / pertanyaan / jawaban / negosiasi. SEMUA validasi (fase,
 * peran, jumlah kata, batas) dilakukan di server — bukan di browser.
 */
class PostService
{
    private const BATAS_PER_MENIT = 12;

    /** @return array{ok:bool,error?:string,post?:array,duplikat?:bool} */
    public static function submit(array $sesi, array $peserta, array $in): array
    {
        $tipe = (string)($in['tipe'] ?? '');
        $key  = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($in['client_key'] ?? '')), 0, 64);
        if ($key === '') {
            return self::err('Permintaan tidak valid (client_key).');
        }

        // Idempoten: kiriman ulang dengan client_key sama mengembalikan hasil yang ada.
        $ada = Database::one('SELECT * FROM cr_post WHERE sesi_id = ? AND user_id = ? AND client_key = ?',
            [$sesi['id'], $peserta['user_id'], $key]);
        if ($ada) {
            return ['ok' => true, 'post' => $ada, 'duplikat' => true];
        }

        if ($sesi['status'] !== 'berjalan') return self::err('Sesi tidak sedang berjalan; input dikunci.');
        if (SesiService::dijeda($sesi))     return self::err('Sesi sedang dijeda dosen.');
        if (!$peserta['kode_peran'])        return self::err('Peran Anda belum dibagikan.');

        $fase = $sesi['fase_aktif'];
        $peran = $peserta['kode_peran'];
        $cfg = config('sesi');

        $n = (int)Database::value('SELECT COUNT(*) FROM cr_post WHERE sesi_id = ? AND user_id = ? AND created_at > (NOW() - INTERVAL 60 SECOND)',
            [$sesi['id'], $peserta['user_id']]);
        if ($n >= self::BATAS_PER_MENIT) return self::err('Terlalu banyak kiriman. Tunggu sebentar.');

        $row = [
            'sesi_id' => (int)$sesi['id'], 'user_id' => (int)$peserta['user_id'], 'kode_peran' => $peran,
            'tipe' => $tipe, 'client_key' => $key,
        ];

        switch ($tipe) {
            case 'argumen':
                if ($fase !== 'argumen') return self::err('Argumen hanya dapat dikirim pada Fase 1.');
                $c = self::bersih($in['claim'] ?? '');
                $e = self::bersih($in['evidence'] ?? '');
                $v = self::bersih($in['nilai_kebangsaan'] ?? '');
                if ($c === '' || $e === '' || $v === '') {
                    return self::err('Kolom Claim, Evidence, dan National Value Alignment wajib diisi semua.');
                }
                $wc = hitung_kata($c) + hitung_kata($e) + hitung_kata($v);
                if ($wc < (int)$sesi['word_min'] || $wc > (int)$sesi['word_max']) {
                    return self::err("Total kata tiga kolom harus {$sesi['word_min']}–{$sesi['word_max']} kata (sekarang {$wc}).", ['jumlah_kata' => $wc]);
                }
                $sudah = (int)Database::value("SELECT COUNT(*) FROM cr_post WHERE sesi_id = ? AND user_id = ? AND tipe = 'argumen'",
                    [$sesi['id'], $peserta['user_id']]);
                if ($sudah >= (int)$cfg['max_argumen_per_mhs']) {
                    return self::err("Batas {$cfg['max_argumen_per_mhs']} argumen per mahasiswa sudah tercapai.");
                }
                $row += ['claim' => $c, 'evidence' => $e, 'nilai_kebangsaan' => $v, 'word_count' => $wc];
                break;

            case 'pertanyaan':
                if ($fase !== 'uji_silang') return self::err('Pertanyaan hanya dapat diajukan pada Fase 2 (Uji Silang).');
                $target = (string)($in['target_peran'] ?? '');
                if (!in_array($target, SesiService::KODE_PERAN, true)) return self::err('Pilih peran yang dituju.');
                if ($target === $peran) return self::err('Pertanyaan harus ditujukan ke peran lain.');
                $isi = self::bersih($in['isi'] ?? '');
                $wc = hitung_kata($isi);
                if ($wc < 3) return self::err('Pertanyaan terlalu pendek.');
                if ($wc > (int)$cfg['pertanyaan_max_kata']) return self::err("Pertanyaan maksimal {$cfg['pertanyaan_max_kata']} kata (sekarang {$wc}).");
                $parent = (int)($in['parent_id'] ?? 0);
                if ($parent) {
                    $a = Database::one("SELECT kode_peran FROM cr_post WHERE id = ? AND sesi_id = ? AND tipe = 'argumen'", [$parent, $sesi['id']]);
                    if (!$a) return self::err('Argumen yang dirujuk tidak ditemukan.');
                    if ($a['kode_peran'] !== $target) return self::err('Argumen yang dirujuk bukan milik peran yang dituju.');
                }
                $row += ['isi' => $isi, 'target_peran' => $target, 'parent_id' => $parent ?: null, 'word_count' => $wc];
                break;

            case 'jawaban':
                if ($fase !== 'uji_silang') return self::err('Jawaban hanya dapat dikirim pada Fase 2 (Uji Silang).');
                $parent = (int)($in['parent_id'] ?? 0);
                $q = $parent ? Database::one("SELECT * FROM cr_post WHERE id = ? AND sesi_id = ? AND tipe = 'pertanyaan'", [$parent, $sesi['id']]) : null;
                if (!$q) return self::err('Pertanyaan tidak ditemukan.');
                if ($q['target_peran'] !== $peran) return self::err('Hanya anggota peran yang dituju yang boleh menjawab.');
                $isi = self::bersih($in['isi'] ?? '');
                $wc = hitung_kata($isi);
                if ($wc < 3) return self::err('Jawaban terlalu pendek.');
                if ($wc > (int)$cfg['jawaban_max_kata']) return self::err("Jawaban maksimal {$cfg['jawaban_max_kata']} kata (sekarang {$wc}).");
                $row += ['isi' => $isi, 'parent_id' => $parent, 'target_peran' => $q['kode_peran'], 'word_count' => $wc];
                break;

            case 'negosiasi':
                if ($fase !== 'negosiasi') return self::err('Panel negosiasi hanya aktif pada Fase 3.');
                $isi = self::bersih($in['isi'] ?? '');
                $len = mb_strlen($isi);
                if ($len < 1) return self::err('Pesan kosong.');
                if ($len > (int)$cfg['negosiasi_max_char']) return self::err("Pesan maksimal {$cfg['negosiasi_max_char']} karakter (sekarang {$len}).");
                $row += ['isi' => $isi, 'word_count' => hitung_kata($isi)];
                break;

            default:
                return self::err('Jenis kiriman tidak dikenal.');
        }

        try {
            $id = Database::insert('cr_post', $row);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {   // balapan pada client_key yang sama
                $ada = Database::one('SELECT * FROM cr_post WHERE sesi_id = ? AND user_id = ? AND client_key = ?',
                    [$sesi['id'], $peserta['user_id'], $key]);
                if ($ada) return ['ok' => true, 'post' => $ada, 'duplikat' => true];
            }
            throw $e;
        }
        return ['ok' => true, 'post' => Database::one('SELECT * FROM cr_post WHERE id = ?', [$id])];
    }

    /** Buang karakter kontrol, rapikan spasi, tanpa mengubah isi. */
    private static function bersih($v): string
    {
        if (!is_string($v)) return '';
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        $v = preg_replace("/\r\n?/", "\n", $v);
        return trim($v);
    }

    private static function err(string $m, array $extra = []): array
    {
        return ['ok' => false, 'error' => $m] + $extra;
    }

    /** Bentuk ringkas post untuk klien. Teks mentah — klien wajib meng-escape. */
    public static function tampil(array $p, array $nama): array
    {
        return [
            'id' => (int)$p['id'], 'user_id' => (int)$p['user_id'], 'nama' => $nama[$p['user_id']] ?? 'Peserta',
            'peran' => $p['kode_peran'], 'tipe' => $p['tipe'],
            'parent_id' => $p['parent_id'] ? (int)$p['parent_id'] : null,
            'target' => $p['target_peran'],
            'claim' => $p['claim'], 'evidence' => $p['evidence'], 'nilai' => $p['nilai_kebangsaan'],
            'isi' => $p['isi'], 'kata' => (int)$p['word_count'],
            'waktu' => date('H:i:s', strtotime($p['created_at'])),
        ];
    }
}
