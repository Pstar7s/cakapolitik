<?php
if (PHP_SAPI !== 'cli') { exit("Jalankan dari command line.\n"); }
/**
 * Uji beban polling: N klien memanggil GET /state serentak (curl_multi) selama beberapa putaran.
 *   php bin/load_test.php <sesi_id> [base_url] [klien=75] [putaran=8]
 * Membutuhkan akun demo (mhs01.. di kelas sesi). Melaporkan latensi per request (p50/p95/maks).
 * Catatan: ini mengukur sisi server (PHP+MySQL) pada mesin lokal — BUKAN pengganti uji di server UB
 * dan tidak mengukur jaringan kampus/Wi-Fi maupun layanan real-time pihak ketiga.
 */
$sid  = (int)($argv[1] ?? 0);
$BASE = rtrim($argv[2] ?? 'http://localhost:8080/cakaproom', '/');
$N    = (int)($argv[3] ?? 75);
$R    = (int)($argv[4] ?? 8);
if (!$sid) { exit("Pemakaian: php bin/load_test.php <sesi_id> [base_url] [klien] [putaran]\n"); }

function login(string $base, string $email, int $sid): string {
    $jar = tempnam(sys_get_temp_dir(), 'lt');
    $get = function ($path, $body = null, $csrf = '') use ($base, $jar) {
        $ch = curl_init($base . $path);
        $h = ['Accept: application/json', 'X-CSRF-Token: ' . $csrf];
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HTTPHEADER => $h];
        if ($body !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); $o[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json'; }
        curl_setopt_array($ch, $o); $r = curl_exec($ch); curl_close($ch); return (string)$r;
    };
    preg_match('/var csrf = "([a-f0-9]+)"/', $get('/login'), $m);
    $j = json_decode($get('/api/auth/otp/request', ['email' => $email], $m[1]), true);
    $v = json_decode($get('/api/auth/otp/verify', ['email' => $email, 'kode' => $j['dev_code']], $m[1]), true);
    if (empty($v['ok'])) throw new Exception('login gagal ' . $email);
    $get("/api/sesi/$sid/join", [], $v['csrf']);   // masuk lobby (idempoten)
    return $jar;
}

echo "Login $N klien…\n";
$jars = [];
for ($i = 1; $i <= $N; $i++) { $jars[$i] = login($BASE, 'mhs' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . '@student.cakaproom.local', $sid); }

$lat = []; $err = 0; $bytes = 0;
for ($round = 1; $round <= $R; $round++) {
    $mh = curl_multi_init(); $hs = [];
    foreach ($jars as $i => $jar) {
        $ch = curl_init("$BASE/api/sesi/$sid/state?since=0&rev=0&res_rev=-1");   // kasus terburuk: tanpa kursor
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30]);
        curl_multi_add_handle($mh, $ch); $hs[$i] = $ch;
    }
    do { curl_multi_exec($mh, $run); curl_multi_select($mh, 0.05); } while ($run > 0);
    foreach ($hs as $ch) {
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $lat[] = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $bytes += (int)curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD); if ($code !== 200) $err++;
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh); usleep(300000);
}
sort($lat); $n = count($lat);
$p = fn($q) => $lat[(int)min($n - 1, floor($q * $n))];
printf("Request: %d (%d klien x %d putaran), galat HTTP: %d\n", $n, $N, $R, $err);
printf("Latensi ms — p50 %.0f · p95 %.0f · p99 %.0f · maks %.0f\n", $p(0.5), $p(0.95), $p(0.99), end($lat));
printf("Rata-rata payload: %.1f KB (kasus terburuk, tanpa kursor)\n", $bytes / $n / 1024);
echo $p(0.95) < 2000 ? "LULUS: p95 < 2 detik\n" : "GAGAL: p95 >= 2 detik\n";
