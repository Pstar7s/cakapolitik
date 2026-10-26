<?php
/**
 * Installer CLI CAKAPROOM.
 *   php bin/migrate.php            -> buat DB + skema + kasus awal (bila kosong)
 *   php bin/migrate.php --fresh    -> DROP semua tabel cr_*, buat ulang skema + kasus awal
 *   php bin/migrate.php --demo     -> tambah akun & kelas DEMO (admin, dosen, 70 mahasiswa). Hanya env 'local'.
 */
if (PHP_SAPI !== 'cli') { exit("Jalankan dari command line.\n"); }

define('ROOT_PATH', dirname(__DIR__));
$config = require ROOT_PATH . '/config/config.php';
$db = $config['db'];
$fresh = in_array('--fresh', $argv, true);
$demo  = in_array('--demo', $argv, true);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$my = new mysqli($db['host'], $db['user'], $db['pass'], '', (int)$db['port']);
$my->set_charset($db['charset']);
$my->query("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$my->select_db($db['name']);
echo "DB: {$db['name']} @ {$db['host']}\n";

$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$has = (int)$my->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . $my->real_escape_string($db['name']) . "' AND table_name = 'cr_users'")->fetch_row()[0];

if ($fresh || !$has) {
    $sql = file_get_contents(ROOT_PATH . '/database/schema.sql');
    $my->multi_query($sql);
    do { if ($r = $my->store_result()) $r->free(); } while ($my->more_results() && $my->next_result());
    echo "  skema dibuat.\n";
    require ROOT_PATH . '/database/seed_kasus.php';
} else {
    echo "Tabel sudah ada. Gunakan --fresh untuk membangun ulang.\n";
    // Upgrade idempoten untuk DB yang dibuat dari versi skema lama.
    $adaKolom = fn($t, $c) => (int)$my->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '" . $my->real_escape_string($db['name']) . "' AND table_name = '{$t}' AND column_name = '{$c}'")->fetch_row()[0];
    if (!$adaKolom('cr_sesi', 'nilai_publik')) {
        $my->query("ALTER TABLE cr_sesi ADD COLUMN nilai_publik TINYINT(1) NOT NULL DEFAULT 0 AFTER res_rev, ADD COLUMN nilai_publik_at DATETIME NULL AFTER nilai_publik");
        echo "  upgrade: kolom cr_sesi.nilai_publik ditambahkan.\n";
    }
}

if ($demo) {
    if (($config['app']['env'] ?? '') !== 'local') { exit("--demo hanya diizinkan pada env 'local'.\n"); }
    $ins = $pdo->prepare('INSERT IGNORE INTO cr_users (role, nama, email, nim) VALUES (?,?,?,?)');
    $ins->execute(['admin', 'Admin Demo', 'admin@cakaproom.local', null]);
    $ins->execute(['dosen', 'Dosen Demo', 'dosen@cakaproom.local', null]);
    $dosenId = (int)$pdo->query("SELECT id FROM cr_users WHERE email = 'dosen@cakaproom.local'")->fetchColumn();
    $pdo->prepare('INSERT INTO cr_kelas (nama_kelas, mata_kuliah, dosen_id) VALUES (?,?,?)')->execute(['Kelas Demo PWK-A', 'Pendidikan Wawasan Kebangsaan', $dosenId]);
    $kelasId = (int)$pdo->lastInsertId();
    $lnk = $pdo->prepare('INSERT IGNORE INTO cr_kelas_mahasiswa (kelas_id, user_id) VALUES (?,?)');
    for ($i = 1; $i <= 70; $i++) {
        $n = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $ins->execute(['mahasiswa', "Mahasiswa Demo {$n}", "mhs{$n}@student.cakaproom.local", "2400000{$n}"]);
        $uid = (int)$pdo->query("SELECT id FROM cr_users WHERE email = 'mhs{$n}@student.cakaproom.local'")->fetchColumn();
        $lnk->execute([$kelasId, $uid]);
    }
    $pdo->exec("UPDATE cr_users SET privasi_ok_at = NOW() WHERE email LIKE '%@cakaproom.local' OR email LIKE '%@student.cakaproom.local'");
    echo "  akun demo: admin@cakaproom.local, dosen@cakaproom.local, mhs01..mhs70@student.cakaproom.local (login via OTP; kode tampil di layar pada env local).\n";
}
echo "Selesai.\n";
