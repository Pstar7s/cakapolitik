<?php
/**
 * CLI installer / migrator.
 *   php bin/migrate.php            -> create schema (if missing) + seed + wilayah (if empty)
 *   php bin/migrate.php --fresh    -> DROP everything, recreate schema + seed + wilayah
 *   php bin/migrate.php --seed     -> (re)run seed only
 *   php bin/migrate.php --wilayah  -> (re)load Jawa Timur region data only
 *   php bin/migrate.php --pejabat  -> (re)load pejabat + peta dapil (BRONE briefing)
 *   php bin/migrate.php --poll     -> (re)load jajak pendapat aspirasi (poll)
 *
 * Reads DB credentials from config/config.php (+ config.local.php).
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

define('ROOT_PATH', dirname(__DIR__));
$config = require ROOT_PATH . '/config/config.php';
$db = $config['db'];

$fresh = in_array('--fresh', $argv, true);
$seedOnly = in_array('--seed', $argv, true);
$wilayahOnly = in_array('--wilayah', $argv, true);
$pejabatOnly = in_array('--pejabat', $argv, true);
$pollOnly = in_array('--poll', $argv, true);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$my = new mysqli($db['host'], $db['user'], $db['pass'], '', (int)$db['port']);
$my->set_charset($db['charset']);
$my->query("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$my->select_db($db['name']);
echo "DB: {$db['name']} @ {$db['host']}\n";

$run = function (string $file) use ($my) {
    $sql = file_get_contents(ROOT_PATH . '/database/' . $file);
    if (!$my->multi_query($sql)) {
        throw new RuntimeException("SQL error in {$file}: " . $my->error);
    }
    do {
        if ($res = $my->store_result()) { $res->free(); }
    } while ($my->more_results() && $my->next_result());
    if ($my->error) {
        throw new RuntimeException("SQL error in {$file}: " . $my->error);
    }
    echo "  ran database/{$file}\n";
};

$hasTables = (int)$my->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . $my->real_escape_string($db['name']) . "'"
)->fetch_row()[0];

if ($wilayahOnly) {
    $run('wilayah_jatim.sql');
    echo "Wilayah Jawa Timur loaded.\n";
    exit;
}

if ($pejabatOnly) {
    $run('pejabat.sql');
    echo "Pejabat + peta dapil loaded.\n";
    exit;
}

if ($pollOnly) {
    $run('poll.sql');
    echo "Jajak pendapat aspirasi (poll) loaded.\n";
    exit;
}

if ($seedOnly) {
    $run('seed.sql');
    echo "Seed done.\n";
    exit;
}

if ($fresh || $hasTables === 0) {
    $run('schema.sql');
    $run('seed.sql');
    $run('wilayah_jatim.sql');
    $run('pejabat.sql');
    $run('poll.sql');
    echo "Fresh install done.\n";
    echo "Admin login -> username: admin  password: cakapolitik#2026  (ganti setelah login!)\n";
    exit;
}

echo "Tables already exist ({$hasTables}). Use --fresh to rebuild or --seed to reseed.\n";
