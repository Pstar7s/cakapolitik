<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\User;
use App\Services\SesiService;

class AdminController extends Controller
{
    public function dashboard(): string
    {
        $u = $this->requireRole('admin');
        $n = [
            'mahasiswa' => (int)Database::value("SELECT COUNT(*) FROM cr_users WHERE role = 'mahasiswa'"),
            'dosen'     => (int)Database::value("SELECT COUNT(*) FROM cr_users WHERE role = 'dosen'"),
            'kelas'     => (int)Database::value('SELECT COUNT(*) FROM cr_kelas'),
            'kasus'     => (int)Database::value("SELECT COUNT(*) FROM cr_kasus WHERE status = 'siap'"),
            'sesi'      => (int)Database::value('SELECT COUNT(*) FROM cr_sesi'),
        ];
        $biaya = Database::one('SELECT COUNT(*) panggilan, COALESCE(SUM(input_tokens),0) masuk, COALESCE(SUM(output_tokens),0) keluar FROM cr_api_log WHERE ok = 1');
        return $this->render('admin/dashboard', ['title' => 'Admin — CAKAPROOM', 'u' => $u, 'n' => $n, 'biaya' => $biaya]);
    }

    // --- Pengguna --------------------------------------------------------------------

    public function pengguna(): string
    {
        $u = $this->requireRole('admin');
        $rows = Database::all("SELECT * FROM cr_users ORDER BY FIELD(role,'admin','dosen','mahasiswa'), nama LIMIT 500");
        return $this->render('admin/pengguna', ['title' => 'Pengguna — CAKAPROOM', 'u' => $u, 'rows' => $rows]);
    }

    public function penggunaTambah(): string
    {
        $this->requireRole('admin');
        $this->verifyCsrf();
        $role = $this->str('role');
        $email = $this->str('email');
        $nama = $this->str('nama');
        if (!in_array($role, ['admin', 'dosen', 'mahasiswa'], true) || $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Isi nama, email valid, dan peran.');
            return $this->redirect('/admin/pengguna');
        }
        if (User::byEmail($email)) {
            flash('error', 'Email sudah terdaftar.');
            return $this->redirect('/admin/pengguna');
        }
        Database::insert('cr_users', ['role' => $role, 'nama' => $nama, 'email' => mb_strtolower($email), 'nim' => $this->str('nim') ?: null]);
        $this->audit('pengguna_tambah', ['email' => $email, 'role' => $role]);
        flash('ok', 'Pengguna ditambahkan.');
        return $this->redirect('/admin/pengguna');
    }

    public function penggunaToggle(int|string $id): string
    {
        $me = $this->requireRole('admin');
        $this->verifyCsrf();
        if ((int)$id === (int)$me['id']) {
            flash('error', 'Tidak dapat menonaktifkan akun sendiri.');
            return $this->redirect('/admin/pengguna');
        }
        Database::run('UPDATE cr_users SET is_active = 1 - is_active WHERE id = ?', [(int)$id]);
        $this->audit('pengguna_toggle', ['id' => (int)$id]);
        return $this->redirect('/admin/pengguna');
    }

    // --- Kelas & roster ----------------------------------------------------------------

    public function kelas(): string
    {
        $u = $this->requireRole('admin');
        $rows = Database::all('SELECT k.*, d.nama AS dosen, (SELECT COUNT(*) FROM cr_kelas_mahasiswa WHERE kelas_id = k.id) AS jml
                                 FROM cr_kelas k LEFT JOIN cr_users d ON d.id = k.dosen_id ORDER BY k.id DESC');
        $dosen = Database::all("SELECT id, nama, email FROM cr_users WHERE role IN ('dosen','admin') AND is_active = 1 ORDER BY nama");
        return $this->render('admin/kelas', ['title' => 'Kelas — CAKAPROOM', 'u' => $u, 'rows' => $rows, 'dosen' => $dosen]);
    }

    public function kelasBuat(): string
    {
        $this->requireRole('admin');
        $this->verifyCsrf();
        $nama = $this->str('nama_kelas');
        if ($nama === '') { flash('error', 'Nama kelas wajib diisi.'); return $this->redirect('/admin/kelas'); }
        $id = Database::insert('cr_kelas', [
            'nama_kelas' => $nama, 'mata_kuliah' => $this->str('mata_kuliah'), 'dosen_id' => $this->int('dosen_id') ?: null,
        ]);
        $this->audit('kelas_buat', ['id' => $id]);
        return $this->redirect('/admin/kelas/' . $id);
    }

    public function kelasDetail(int|string $id): string
    {
        $u = $this->requireRole('admin');
        $kelas = Database::one('SELECT * FROM cr_kelas WHERE id = ?', [(int)$id]);
        if (!$kelas) { http_response_code(404); return $this->render('errors/404', []); }
        $mhs = Database::all('SELECT u.* FROM cr_kelas_mahasiswa km JOIN cr_users u ON u.id = km.user_id WHERE km.kelas_id = ? ORDER BY u.nama', [$kelas['id']]);
        return $this->render('admin/kelas_detail', ['title' => 'Roster — CAKAPROOM', 'u' => $u, 'kelas' => $kelas, 'mhs' => $mhs]);
    }

    /** Impor roster: file CSV (kolom nama,email,nim) atau teks tempel. */
    public function kelasImport(int|string $id): string
    {
        $this->requireRole('admin');
        $this->verifyCsrf();
        $kelas = Database::one('SELECT id FROM cr_kelas WHERE id = ?', [(int)$id]);
        if (!$kelas) { http_response_code(404); return $this->render('errors/404', []); }

        $raw = '';
        if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            if ($_FILES['csv']['size'] > 1024 * 1024) {
                flash('error', 'Berkas CSV terlalu besar (maks 1 MB).');
                return $this->redirect('/admin/kelas/' . (int)$id);
            }
            $raw = (string)file_get_contents($_FILES['csv']['tmp_name']);
        } else {
            $raw = (string)($_POST['teks'] ?? '');
        }
        $r = self::imporRoster((int)$id, $raw);
        $this->audit('roster_impor', ['kelas' => (int)$id] + array_diff_key($r, ['galat' => 1]));
        flash('ok', "Impor selesai: {$r['baru']} akun baru, {$r['ada']} sudah ada, {$r['ditambah']} masuk roster, {$r['lewat']} dilewati.");
        if ($r['galat']) {
            flash('error', implode(' | ', array_slice($r['galat'], 0, 8)));
        }
        return $this->redirect('/admin/kelas/' . (int)$id);
    }

    /** @return array{baru:int,ada:int,ditambah:int,lewat:int,galat:array} */
    public static function imporRoster(int $kelasId, string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? '';
        $lines = preg_split('/\R/u', trim($raw)) ?: [];
        $r = ['baru' => 0, 'ada' => 0, 'ditambah' => 0, 'lewat' => 0, 'galat' => []];
        if (!$lines || $lines === ['']) return $r;

        $delim = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $head = array_map(fn($h) => mb_strtolower(trim($h)), str_getcsv($lines[0], $delim));
        $punyaHeader = in_array('email', $head, true);
        $idx = ['nama' => 0, 'email' => 1, 'nim' => 2];
        if ($punyaHeader) {
            $idx = ['nama' => array_search('nama', $head, true), 'email' => array_search('email', $head, true), 'nim' => array_search('nim', $head, true)];
            array_shift($lines);
        }
        foreach ($lines as $n => $line) {
            if (trim($line) === '') continue;
            $c = str_getcsv($line, $delim);
            $nama  = $idx['nama'] !== false ? trim($c[$idx['nama']] ?? '') : '';
            $email = $idx['email'] !== false ? trim($c[$idx['email']] ?? '') : '';
            $nim   = $idx['nim'] !== false ? trim($c[$idx['nim']] ?? '') : '';
            if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $r['lewat']++;
                $r['galat'][] = 'Baris ' . ($n + ($punyaHeader ? 2 : 1)) . ': nama/email tidak valid';
                continue;
            }
            $ex = User::byEmail($email);
            if ($ex && $ex['role'] !== 'mahasiswa') {
                $r['lewat']++;
                $r['galat'][] = "{$email}: sudah terdaftar sebagai {$ex['role']}";
                continue;
            }
            [$uid, $baru] = User::upsert('mahasiswa', mb_substr($nama, 0, 120), $email, $nim !== '' ? mb_substr($nim, 0, 32) : null);
            $baru ? $r['baru']++ : $r['ada']++;
            $ins = Database::run('INSERT IGNORE INTO cr_kelas_mahasiswa (kelas_id, user_id) VALUES (?, ?)', [$kelasId, $uid]);
            $r['ditambah'] += $ins->rowCount();
        }
        return $r;
    }

    // --- Kasus ------------------------------------------------------------------------

    public function kasusIndex(): string
    {
        $u = $this->requireRole('admin');
        $rows = Database::all('SELECT id, judul, mata_kuliah, status, rubrik_status, created_at FROM cr_kasus ORDER BY id DESC');
        return $this->render('admin/kasus', ['title' => 'Kasus — CAKAPROOM', 'u' => $u, 'rows' => $rows]);
    }

    public function kasusBaru(): string
    {
        $u = $this->requireRole('admin');
        return $this->render('admin/kasus_form', ['title' => 'Kasus Baru — CAKAPROOM', 'u' => $u, 'k' => null, 'briefs' => [], 'klausul' => []]);
    }

    public function kasusEdit(int|string $id): string
    {
        $u = $this->requireRole('admin');
        $k = Database::one('SELECT * FROM cr_kasus WHERE id = ?', [(int)$id]);
        if (!$k) { http_response_code(404); return $this->render('errors/404', []); }
        $briefs = array_column(Database::all('SELECT kode_peran, brief_peran FROM cr_kasus_peran WHERE kasus_id = ?', [$k['id']]), 'brief_peran', 'kode_peran');
        $klausul = Database::all('SELECT * FROM cr_kasus_klausul WHERE kasus_id = ? ORDER BY no', [$k['id']]);
        $dipakai = (int)Database::value('SELECT COUNT(*) FROM cr_sesi WHERE kasus_id = ?', [$k['id']]);
        return $this->render('admin/kasus_form', ['title' => 'Edit Kasus — CAKAPROOM', 'u' => $u, 'k' => $k, 'briefs' => $briefs, 'klausul' => $klausul, 'dipakai' => $dipakai]);
    }

    public function kasusSimpan(int|string $id = 0): string
    {
        $this->requireRole('admin');
        $this->verifyCsrf();
        $id = (int)$id;
        $judul = $this->str('judul');
        $latar = $this->str('latar_belakang');
        $rubrik = trim((string)($_POST['rubrik_json'] ?? ''));
        $klausul = [];
        foreach (($_POST['klausul_judul'] ?? []) as $i => $j) {
            $j = trim((string)$j);
            if ($j !== '') $klausul[] = [$j, trim((string)($_POST['klausul_desk'][$i] ?? ''))];
        }

        $err = null;
        if ($judul === '' || $latar === '') $err = 'Judul dan latar belakang wajib diisi.';
        elseif ($rubrik !== '' && json_decode($rubrik) === null) $err = 'Rubrik harus berupa JSON yang valid.';
        elseif ($this->str('status') === 'siap') {
            foreach (SesiService::KODE_PERAN as $p) {
                if (trim((string)($_POST['brief'][$p] ?? '')) === '') { $err = "Brief peran " . peran_label($p) . " wajib diisi agar kasus berstatus siap."; break; }
            }
            if (!$err && !$klausul) $err = 'Minimal satu klausul Joint Resolution diperlukan agar kasus berstatus siap.';
        }
        if ($err) {
            flash('error', $err);
            return $this->redirect($id ? "/admin/kasus/{$id}/edit" : '/admin/kasus/baru');
        }

        $data = [
            'judul' => $judul, 'mata_kuliah' => $this->str('mata_kuliah'), 'tujuan' => $this->str('tujuan'),
            'latar_belakang' => $latar, 'referensi_data' => (string)($_POST['referensi_data'] ?? ''),
            'pertanyaan_kunci' => $this->str('pertanyaan_kunci'),
            'rubrik_json' => $rubrik !== '' ? $rubrik : null,
            'rubrik_status' => $this->str('rubrik_status') === 'disetujui' ? 'disetujui' : 'sementara',
            'status' => $this->str('status') === 'siap' ? 'siap' : 'draft',
        ];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id) {
                Database::update('cr_kasus', $data, 'id = :id', ['id' => $id]);
            } else {
                $id = Database::insert('cr_kasus', $data);
            }
            foreach (SesiService::KODE_PERAN as $p) {
                Database::run('INSERT INTO cr_kasus_peran (kasus_id, kode_peran, brief_peran) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE brief_peran = VALUES(brief_peran)', [$id, $p, (string)($_POST['brief'][$p] ?? '')]);
            }
            // Klausul: nomor berurutan; sesi yang sudah berjalan tetap memakai baris klausul sesi sendiri.
            Database::run('DELETE FROM cr_kasus_klausul WHERE kasus_id = ?', [$id]);
            foreach ($klausul as $i => [$j, $d]) {
                Database::insert('cr_kasus_klausul', ['kasus_id' => $id, 'no' => $i + 1, 'judul' => mb_substr($j, 0, 160), 'deskripsi' => $d]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->audit('kasus_simpan', ['id' => $id]);
        flash('ok', 'Kasus disimpan.');
        return $this->redirect("/admin/kasus/{$id}/edit");
    }

    public function kasusHapus(int|string $id): string
    {
        $this->requireRole('admin');
        $this->verifyCsrf();
        if ((int)Database::value('SELECT COUNT(*) FROM cr_sesi WHERE kasus_id = ?', [(int)$id]) > 0) {
            flash('error', 'Kasus sudah dipakai sesi; tidak dapat dihapus. Ubah statusnya menjadi draft.');
            return $this->redirect('/admin/kasus');
        }
        Database::run('DELETE FROM cr_kasus WHERE id = ?', [(int)$id]);
        $this->audit('kasus_hapus', ['id' => (int)$id]);
        return $this->redirect('/admin/kasus');
    }
}
