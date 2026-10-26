<?php
namespace App\Models;

use App\Core\Database;

/**
 * Jajak pendapat aspirasi ("poll") yang disajikan BRONE saat warga menutup sesi.
 * Fase 1: hanya tipe 'ganda' (1 pilihan per pertanyaan). Lihat database/poll.sql.
 */
class Poll extends Model
{
    protected static string $table = 'poll_pertanyaan';

    public static function available(): bool
    {
        try {
            return (int)Database::value('SELECT COUNT(*) FROM poll_pertanyaan LIMIT 1') >= 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Pertanyaan aktif + opsinya (aktif), siap dikirim ke klien / dirender.
     * @return array<int,array<string,mixed>>
     */
    public static function activeQuestions(): array
    {
        $qs = Database::all(
            'SELECT id, judul, deskripsi, kategori, tipe, max_pilihan
               FROM poll_pertanyaan
              WHERE is_aktif = 1
              ORDER BY urutan ASC, id ASC'
        );
        if (!$qs) {
            return [];
        }
        $ids = implode(',', array_map(fn($q) => (int)$q['id'], $qs));
        $opts = Database::all(
            "SELECT id, pertanyaan_id, label
               FROM poll_opsi
              WHERE is_aktif = 1 AND pertanyaan_id IN ($ids)
              ORDER BY urutan ASC, id ASC"
        );
        $byQ = [];
        foreach ($opts as $o) {
            $byQ[(int)$o['pertanyaan_id']][] = ['id' => (int)$o['id'], 'label' => $o['label']];
        }
        foreach ($qs as &$q) {
            $q['id']          = (int)$q['id'];
            $q['max_pilihan'] = (int)$q['max_pilihan'];
            $q['opsi']        = $byQ[$q['id']] ?? [];
        }
        unset($q);
        // Hanya pertanyaan yang punya minimal 1 opsi aktif.
        return array_values(array_filter($qs, fn($q) => count($q['opsi']) > 0));
    }

    /**
     * Pertanyaan yang masih perlu dijawab warga ini. Fase 1: seluruh poll aktif
     * (pemeriksaan "sudah isi" ditangani lewat flag flow di chat).
     * @return array<int,array<string,mixed>>
     */
    public static function pendingQuestions(int $wargaId): array
    {
        return self::activeQuestions();
    }

    /**
     * Simpan jawaban warga. $answers = [pertanyaan_id => opsi_id]. Idempoten:
     * jawaban lama warga untuk pertanyaan yang sama diganti (bisa revisi).
     */
    public static function record(int $wargaId, string $sesi, array $answers, array $warga, ?int $dapilId): int
    {
        if (!$answers) {
            return 0;
        }
        $valid = self::validOptionMap();
        $saved = 0;
        $pdo = Database::pdo();
        foreach ($answers as $qid => $oid) {
            $qid = (int)$qid;
            $oid = (int)$oid;
            if (!isset($valid[$qid]) || !in_array($oid, $valid[$qid], true)) {
                continue; // opsi tidak valid / bukan milik pertanyaan aktif
            }
            Database::run(
                'DELETE FROM poll_jawaban WHERE warga_id = ? AND pertanyaan_id = ?',
                [$wargaId, $qid]
            );
            Database::insert('poll_jawaban', [
                'warga_id'      => $wargaId,
                'pertanyaan_id' => $qid,
                'opsi_id'       => $oid,
                'sesi'          => $sesi ?: null,
                'kab_kota'      => $warga['kab_kota'] ?? null,
                'kecamatan'     => $warga['kecamatan'] ?? null,
                'dapil_id'      => $dapilId,
            ]);
            $saved++;
        }
        return $saved;
    }

    /** @return array<int,array<int,int>> pertanyaan_id => [opsi_id,...] (aktif) */
    private static function validOptionMap(): array
    {
        $rows = Database::all(
            'SELECT o.id, o.pertanyaan_id
               FROM poll_opsi o
               JOIN poll_pertanyaan q ON q.id = o.pertanyaan_id
              WHERE o.is_aktif = 1 AND q.is_aktif = 1'
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['pertanyaan_id']][] = (int)$r['id'];
        }
        return $map;
    }

    /**
     * Hasil agregat untuk dashboard.
     * @return array<int,array{id:int,judul:string,kategori:?string,responden:int,opsi:array<int,array{label:string,jumlah:int}>}>
     */
    public static function results(): array
    {
        $out = [];
        foreach (self::activeQuestions() as $q) {
            $rows = Database::all(
                'SELECT o.id, o.label, COUNT(j.id) AS jumlah
                   FROM poll_opsi o
                   LEFT JOIN poll_jawaban j ON j.opsi_id = o.id
                  WHERE o.pertanyaan_id = ? AND o.is_aktif = 1
                  GROUP BY o.id, o.label, o.urutan
                  ORDER BY o.urutan ASC, o.id ASC',
                [$q['id']]
            );
            $responden = (int)Database::value(
                'SELECT COUNT(DISTINCT warga_id) FROM poll_jawaban WHERE pertanyaan_id = ?',
                [$q['id']]
            );
            $out[] = [
                'id'        => $q['id'],
                'judul'     => $q['judul'],
                'kategori'  => $q['kategori'],
                'responden' => $responden,
                'opsi'      => array_map(fn($r) => [
                    'label'  => $r['label'],
                    'jumlah' => (int)$r['jumlah'],
                ], $rows),
            ];
        }
        return $out;
    }

    public static function totalResponden(): int
    {
        return (int)Database::value('SELECT COUNT(DISTINCT warga_id) FROM poll_jawaban');
    }

    public static function countJawaban(int $pertanyaanId): int
    {
        return (int)Database::value(
            'SELECT COUNT(*) FROM poll_jawaban WHERE pertanyaan_id = ?',
            [$pertanyaanId]
        );
    }

    // --- Admin helpers ----------------------------------------------------

    /** @return array<int,array<string,mixed>> semua pertanyaan + jumlah opsi & jawaban */
    public static function allForAdmin(): array
    {
        $qs = Database::all('SELECT * FROM poll_pertanyaan ORDER BY urutan ASC, id ASC');
        foreach ($qs as &$q) {
            $q['opsi_count'] = (int)Database::value(
                'SELECT COUNT(*) FROM poll_opsi WHERE pertanyaan_id = ?', [$q['id']]
            );
            $q['jawaban_count'] = self::countJawaban((int)$q['id']);
        }
        unset($q);
        return $qs;
    }

    /** @return array<int,array<string,mixed>> */
    public static function optionsFor(int $pertanyaanId): array
    {
        return Database::all(
            'SELECT * FROM poll_opsi WHERE pertanyaan_id = ? ORDER BY urutan ASC, id ASC',
            [$pertanyaanId]
        );
    }
}
