<?php
if (PHP_SAPI !== 'cli') { exit("Jalankan dari command line.\n"); }
/**
 * Uji asap end-to-end via HTTP (butuh Apache + DB demo: php bin/migrate.php --fresh --demo).
 *   php bin/smoke_test.php [base_url] [jumlah_mahasiswa]
 * Memverifikasi: login OTP, pembagian peran (selisih <=1, tetap setelah reconnect),
 * validasi server, idempotensi, uji silang, negosiasi, kunci klausul, RBAC, skor AI,
 * nilai kelompok & individual. Tidak mengubah kode aplikasi.
 */
$BASE = rtrim($argv[1] ?? 'http://localhost:8080/cakaproom', '/');
$N    = (int)($argv[2] ?? 70);
$fail = 0; $pass = 0;

function ok($cond, $msg) { global $fail, $pass; if ($cond) { $pass++; echo "  [ok]   $msg\n"; } else { $fail++; echo "  [GAGAL] $msg\n"; } }

class C {
    public $jar; public $csrf = ''; public $base;
    function __construct($base) { $this->base = $base; $this->jar = tempnam(sys_get_temp_dir(), 'crj'); }
    function req($method, $path, $body = null, $json = true) {
        $ch = curl_init($this->base . $path);
        $h = ['Accept: application/json', 'X-CSRF-Token: ' . $this->csrf];
        if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 120]);
        $raw = curl_exec($ch); $st = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if (!$json) return [$st, $raw];
        $j = json_decode($raw, true); if (!is_array($j)) $j = ['ok' => false, 'raw' => substr((string)$raw, 0, 200)];
        $j['_s'] = $st; return $j;
    }
    function login($email) {
        [, $html] = $this->req('GET', '/login', null, false);
        // csrf dari halaman login (var csrf = "...")
        if (!preg_match('/var csrf = "([a-f0-9]+)"/', $html, $m)) throw new Exception('csrf tidak ditemukan');
        $this->csrf = $m[1];
        $r = $this->req('POST', '/api/auth/otp/request', ['email' => $email]);
        if (empty($r['dev_code'])) throw new Exception("OTP tidak ada untuk $email: " . json_encode($r));
        $v = $this->req('POST', '/api/auth/otp/verify', ['email' => $email, 'kode' => $r['dev_code']]);
        if (empty($v['ok'])) throw new Exception("verify gagal: " . json_encode($v));
        $this->csrf = $v['csrf'];
        return $v;
    }
}

$t0 = microtime(true);
echo "== Login ==\n";
$dosen = new C($BASE); $dosen->login('dosen@cakaproom.local'); ok(true, 'dosen login via OTP');
$admin = new C($BASE); $admin->login('admin@cakaproom.local'); ok(true, 'admin login via OTP');
$bad = new C($BASE); [, $h] = $bad->req('GET', '/login', null, false); preg_match('/var csrf = "([a-f0-9]+)"/', $h, $m); $bad->csrf = $m[1];
$r = $bad->req('POST', '/api/auth/otp/verify', ['email' => 'dosen@cakaproom.local', 'kode' => '000000']);
ok(empty($r['ok']), 'kode OTP salah ditolak');
$r = $bad->req('POST', '/api/auth/otp/request', ['email' => 'tidak.ada@x.com']);
ok(!isset($r['dev_code']), 'email tak terdaftar tidak membocorkan kode');

echo "== Buat sesi ==\n";
$kelasId = 1; $kasusId = 1;
[$st] = $dosen->req('POST', '/dosen/sesi', null, false);
ok($st === 419 || $st === 302 || $st === 200, 'POST form tanpa CSRF tidak melempar error server (status ' . $st . ')');
// buat sesi lewat controller (form) — kirim sebagai form dengan token
$ch = curl_init("$BASE/dosen/sesi");
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $dosen->jar, CURLOPT_COOKIEFILE => $dosen->jar,
    CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $dosen->csrf, 'kelas_id' => $kelasId, 'kasus_id' => $kasusId, 'judul' => 'Smoke test', 'word_min' => 100, 'word_max' => 200])]);
$resp = curl_exec($ch); curl_close($ch);
preg_match('#Location: .*/dosen/sesi/(\d+)#i', $resp, $m);
$sid = (int)($m[1] ?? 0); ok($sid > 0, "sesi dibuat (id $sid)");
if (!$sid) exit(1);
$A = "/api/sesi/$sid"; $D = "/api/dosen/sesi/$sid";

echo "== $N mahasiswa login & join lobby ==\n";
$mhs = [];
for ($i = 1; $i <= $N; $i++) {
    $c = new C($BASE); $n = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
    $c->login("mhs{$n}@student.cakaproom.local"); $mhs[$i] = $c;
    $j = $c->req('POST', "$A/join", []);
    if (empty($j['ok'])) { ok(false, "join mhs$n: " . json_encode($j)); }
}
$st = $dosen->req('GET', "$D/state");
ok(($st['stat']['bergabung'] ?? 0) === $N, "dosen melihat $N peserta di lobby");

echo "== Bagi peran ==\n";
$r = $dosen->req('POST', "$D/bagi-peran", []);
$cnt = $r['per_peran'] ?? []; ok($r['ok'] ?? false, 'bagi peran ok: ' . json_encode($cnt));
ok(max($cnt) - min($cnt) <= 1, 'selisih antar-peran <= 1');
$r2 = $dosen->req('POST', "$D/bagi-peran", []);
ok(($r2['dibagi'] ?? -1) === 0, 'bagi-peran kedua tidak mengubah peran yang ada');
$peran = []; $deleg = [];
foreach ($mhs as $i => $c) { $s = $c->req('GET', "$A/state"); $peran[$i] = $s['me']['peran'] ?? null; $deleg[$i] = $s['me']['delegasi'] ?? false; }
ok(!in_array(null, $peran, true), 'semua mahasiswa punya peran');
$byPeran = array_count_values($peran); ok(count($byPeran) === 4, 'keempat peran terisi');
$dg = []; foreach ($peran as $i => $p) if ($deleg[$i]) $dg[$p] = ($dg[$p] ?? 0) + 1;
ok(count($dg) === 4 && min($dg) === 2 && max($dg) === 2, 'tiap peran punya 2 delegasi: ' . json_encode($dg));
// mahasiswa lain tidak boleh memanggil endpoint dosen
$r = $mhs[1]->req('POST', "$D/fase", ['aksi' => 'mulai']); ok(($r['_s'] ?? 0) === 403, 'RBAC: mahasiswa ditolak pada endpoint dosen (403)');
$r = $mhs[1]->req('GET', "/api/dosen/sesi/$sid/state"); ok(($r['_s'] ?? 0) === 403, 'RBAC: state dosen ditolak untuk mahasiswa');
$anon = new C($BASE); $r = $anon->req('GET', "$A/state"); ok(($r['_s'] ?? 0) === 401, 'tanpa login -> 401');

echo "== Fase 1: argumen ==\n";
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'argumen', 'claim' => 'a', 'evidence' => 'b', 'nilai_kebangsaan' => 'c', 'client_key' => 'k0']);
ok(empty($r['ok']), 'argumen sebelum sesi dimulai ditolak: ' . ($r['error'] ?? ''));
$r = $dosen->req('POST', "$D/fase", ['aksi' => 'mulai']); ok($r['ok'] ?? false, 'sesi dimulai (fase argumen)');
function kata($n, $kata = 'data') { return implode(' ', array_fill(0, $n, $kata)); }
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'argumen', 'claim' => '', 'evidence' => kata(120), 'nilai_kebangsaan' => kata(10), 'client_key' => 'k1']);
ok(empty($r['ok']) && $r['_s'] === 422, 'kolom kosong ditolak server');
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'argumen', 'claim' => kata(10), 'evidence' => kata(30), 'nilai_kebangsaan' => kata(10), 'client_key' => 'k2']);
ok(empty($r['ok']) && ($r['jumlah_kata'] ?? 0) === 50, 'total kata < 100 ditolak (' . ($r['error'] ?? '') . ')');
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'argumen', 'claim' => kata(60), 'evidence' => kata(100), 'nilai_kebangsaan' => kata(60), 'client_key' => 'k3']);
ok(empty($r['ok']), 'total kata > 200 ditolak');
$argIds = [];
foreach ($mhs as $i => $c) {
    $body = ['tipe' => 'argumen', 'claim' => 'Klausul jadwal harus bertahap. ' . kata(20), 'evidence' => 'Data kasus menyebut 9.800 pekerja dan Pasal 33 UUD 1945. ' . kata(60), 'nilai_kebangsaan' => 'Sesuai sila ke-5 keadilan sosial. ' . kata(25), 'client_key' => "arg-$i"];
    $r = $c->req('POST', "$A/post", $body);
    if (empty($r['ok'])) { ok(false, "argumen mhs$i: " . json_encode($r)); continue; }
    $argIds[$i] = $r['id'];
    if ($i === 1) { $r2 = $c->req('POST', "$A/post", $body); ok(($r2['ok'] ?? false) && ($r2['id'] ?? 0) === $r['id'] && !empty($r2['duplikat']), 'kirim ulang client_key sama idempoten (tanpa dobel)'); }
}
ok(count($argIds) === $N, "$N argumen valid terkirim");
$s = $mhs[2]->req('GET', "$A/state?since=0&rev=0&res_rev=-1");
ok(count($s['posts'] ?? []) === $N, 'mahasiswa lain melihat semua argumen via state');
$s2 = $mhs[2]->req('GET', "$A/state?since=" . max(array_column($s['posts'], 'id')) . "&rev={$s['sesi']['rev']}&res_rev=-1");
ok(empty($s2['posts']) && !isset($s2['peserta']), 'state?since hanya mengirim delta');
for ($k = 0; $k < 3; $k++) { $c = $mhs[3]->req('POST', "$A/post", ['tipe' => 'argumen', 'claim' => kata(30), 'evidence' => kata(60), 'nilai_kebangsaan' => kata(30), 'client_key' => "extra$k"]); }
ok(empty($c['ok']), 'batas argumen per mahasiswa ditegakkan: ' . ($c['error'] ?? ''));

echo "== Fase 2: uji silang ==\n";
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'pertanyaan', 'target_peran' => 'SIPIL', 'isi' => kata(5), 'client_key' => 'q0']);
ok(empty($r['ok']), 'pertanyaan di fase 1 ditolak');
$dosen->req('POST', "$D/fase", ['aksi' => 'berikutnya']);
$p1 = $peran[1]; $target = $p1 === 'SIPIL' ? 'SWASTA' : 'SIPIL';
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'pertanyaan', 'target_peran' => $p1, 'isi' => kata(5), 'client_key' => 'q1']);
ok(empty($r['ok']), 'pertanyaan ke peran sendiri ditolak');
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'pertanyaan', 'target_peran' => $target, 'isi' => kata(60), 'client_key' => 'q2']);
ok(empty($r['ok']), 'pertanyaan > 50 kata ditolak');
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'pertanyaan', 'target_peran' => $target, 'isi' => 'Berapa dana yang berani dikomitmenkan dan dengan jaminan apa?', 'client_key' => 'q3']);
ok($r['ok'] ?? false, 'pertanyaan valid terkirim'); $qid = $r['id'] ?? 0;
$anggotaTarget = array_keys(array_filter($peran, fn($p) => $p === $target)); $bukanTarget = array_keys(array_filter($peran, fn($p) => $p !== $target));
$r = $mhs[$bukanTarget[0]]->req('POST', "$A/post", ['tipe' => 'jawaban', 'parent_id' => $qid, 'isi' => 'Saya menjawab pertanyaan ini dengan baik sekali.', 'client_key' => 'a0']);
ok(empty($r['ok']), 'peran lain tidak boleh menjawab: ' . ($r['error'] ?? ''));
$r = $mhs[$anggotaTarget[0]]->req('POST', "$A/post", ['tipe' => 'jawaban', 'parent_id' => $qid, 'isi' => 'Kami berkomitmen menyetor dana jaminan sebelum produksi tahap berikutnya.', 'client_key' => 'a1']);
ok($r['ok'] ?? false, 'anggota peran yang dituju menjawab (thread)');

echo "== Fase 3: negosiasi & Joint Resolution ==\n";
$dosen->req('POST', "$D/fase", ['aksi' => 'berikutnya']);
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'negosiasi', 'isi' => str_repeat('x', 300), 'client_key' => 'n0']);
ok(empty($r['ok']), 'pesan negosiasi > 280 karakter ditolak');
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'negosiasi', 'isi' => 'Kami usul jadwal bertahap 2035 dengan jaminan pekerja.', 'client_key' => 'n1']);
ok($r['ok'] ?? false, 'pesan negosiasi terkirim');
$delIdx = array_keys(array_filter($deleg)); $nonDel = array_keys(array_filter($deleg, fn($d) => !$d));
$r = $mhs[$nonDel[0]]->req('POST', "$A/resolusi/kunci", ['no' => 1]); ok(empty($r['ok']) && $r['_s'] === 403, 'non-delegasi tidak dapat mengunci klausul');
$d1 = $delIdx[0]; $d2 = null; foreach ($delIdx as $d) if ($d !== $d1) { $d2 = $d; break; }
$r = $mhs[$d1]->req('POST', "$A/resolusi/kunci", ['no' => 1]); ok($r['ok'] ?? false, 'delegasi 1 mengunci klausul 1');
$r = $mhs[$d2]->req('POST', "$A/resolusi/kunci", ['no' => 1]); ok(empty($r['ok']) && !empty($r['terkunci']), 'delegasi 2 ditolak (klausul sedang disunting): ' . ($r['error'] ?? ''));
$r = $mhs[$d2]->req('POST', "$A/resolusi/simpan", ['no' => 1, 'teks' => 'hack']); ok(empty($r['ok']), 'simpan tanpa kunci ditolak');
$r = $mhs[$d1]->req('POST', "$A/resolusi/simpan", ['no' => 1, 'teks' => "Jadwal: penurunan produksi bertahap 2030-2038; PLTU berhenti 2040 dengan syarat jaminan pekerja.", 'lepas' => true]); ok($r['ok'] ?? false, 'delegasi 1 menyimpan & melepas klausul 1');
$sudahKlausul = [1];
foreach ([2, 3, 4, 5] as $no) {
    $dlg = $delIdx[$no % count($delIdx)];
    $mhs[$dlg]->req('POST', "$A/resolusi/kunci", ['no' => $no]);
    $r = $mhs[$dlg]->req('POST', "$A/resolusi/simpan", ['no' => $no, 'teks' => "Klausul $no disepakati bersama dengan rincian pembiayaan dan pengawasan terbuka.", 'lepas' => true]);
    if ($r['ok'] ?? false) $sudahKlausul[] = $no;
}
ok(count($sudahKlausul) === 5, 'kelima klausul terisi');
// sikap tiap peran (delegasi dari peran masing-masing)
foreach (['PEMERINTAH', 'SIPIL', 'SWASTA', 'WARGA'] as $pk) {
    $d = null; foreach ($delIdx as $i) if ($peran[$i] === $pk) { $d = $i; break; }
    $r = $mhs[$d]->req('POST', "$A/resolusi/sikap", ['sikap' => $pk === 'WARGA' ? 'keberatan' : 'setuju', 'catatan' => 'Keberatan karena jaminan pekerja belum tertulis rinci.']);
    if (!($r['ok'] ?? false)) ok(false, "sikap $pk: " . json_encode($r));
}
$s = $mhs[5]->req('GET', "$A/state?since=0&rev=0&res_rev=-1");
ok(count($s['resolusi']['klausul'] ?? []) === 5 && count($s['resolusi']['sikap'] ?? []) === 4, 'semua peserta melihat Joint Resolution live (5 klausul, 4 sikap)');
$r = $mhs[$nonDel[0]]->req('POST', "$A/resolusi/sikap", ['sikap' => 'setuju']); ok(empty($r['ok']), 'non-delegasi tidak dapat mencatat sikap');

echo "== Jeda & lanjut ==
";
$dosen->req('POST', "$D/fase", ['aksi' => 'jeda']);
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'negosiasi', 'isi' => 'saat dijeda', 'client_key' => 'n2']); ok(empty($r['ok']), 'posting ditolak saat dijeda');
$s = $mhs[1]->req('GET', "$A/state"); ok(($s['sesi']['dijeda'] ?? false) === true, 'klien menerima status dijeda');
$dosen->req('POST', "$D/fase", ['aksi' => 'lanjut']);
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'negosiasi', 'isi' => 'setelah lanjut', 'client_key' => 'n3']); ok($r['ok'] ?? false, 'posting kembali normal setelah lanjut');
$a = $dosen->req('GET', "$D/state"); $b = $mhs[7]->req('GET', "$A/state");
ok(abs(($a['sesi']['sisa'] ?? 0) - ($b['sesi']['sisa'] ?? 99999)) <= 2, 'timer dosen & mahasiswa selisih <= 2 detik');

echo "== Akhiri & penilaian ==
";
$r = $dosen->req('POST', "$D/skor-ai", []); ok(empty($r['ok']), 'skor AI sebelum sesi berakhir ditolak');
$dosen->req('POST', "$D/fase", ['aksi' => 'berikutnya']);
$r = $dosen->req('POST', "$D/fase", ['aksi' => 'akhiri']); ok($r['ok'] ?? false, 'sesi diakhiri');
$r = $mhs[1]->req('POST', "$A/post", ['tipe' => 'negosiasi', 'isi' => 'setelah berakhir', 'client_key' => 'n4']); ok(empty($r['ok']), 'input terkunci setelah sesi berakhir');
$r = $dosen->req('POST', "$D/skor-ai", []); ok($r['ok'] ?? false, 'skor awal AI dihitung');
$skor = $r['skor'] ?? []; ok(count($skor) === 4, 'skor AI untuk keempat kelompok');
foreach ($skor as $pk => $x) { $t = round(0.35*$x['A'] + 0.35*$x['B'] + 0.30*$x['C'], 2); if (abs($t - $x['total']) > 0.01) ok(false, "total $pk salah"); }
ok(true, 'total berbobot 35/35/30 dihitung server benar');
$r = $mhs[1]->req('POST', "$D/nilai-final", ['peran' => 'SIPIL', 'A' => 80, 'B' => 80, 'C' => 80]); ok(($r['_s'] ?? 0) === 403, 'mahasiswa tidak dapat menetapkan nilai');
$r = $dosen->req('POST', "$D/nilai-final", ['peran' => 'SIPIL', 'A' => 150, 'B' => 80, 'C' => 80]); ok(empty($r['ok']), 'skor di luar 0-100 ditolak');
foreach (['PEMERINTAH', 'SIPIL', 'SWASTA', 'WARGA'] as $pk) {
    $x = $skor[$pk]; $over = $pk === 'SIPIL';
    $r = $dosen->req('POST', "$D/nilai-final", ['peran' => $pk, 'A' => $over ? 90 : $x['A'], 'B' => $x['B'], 'C' => $x['C'], 'catatan' => 'uji']);
    ok(($r['ok'] ?? false) && ($r['sumber'] === ($over ? 'override' : 'ai')), "nilai final $pk (sumber {$r['sumber']})");
}
$pen = $dosen->req('GET', "$D/penilaian"); ok($pen['status'] === 'dinilai' && count($pen['final']) === 4, 'status sesi menjadi dinilai');
ok(isset($pen['skor_ai']['SIPIL']) && (float)$pen['skor_ai']['SIPIL']['A'] !== 90.0, 'skor AI tetap tersimpan terpisah dari nilai final (override)');

echo "== Nilai individual ==
";
$ind = $dosen->req('GET', "$D/individu"); ok(count($ind['rows'] ?? []) === $N, "tabel individu memuat $N mahasiswa");
$row = $ind['rows'][0]; $uid = $row['user_id'];
ok((float)$row['nilai_individu'] === (float)$row['nilai_kelompok'], 'default nilai individu = nilai kelompok');
$r = $dosen->req('POST', "$D/individu/$uid", ['nilai' => (float)$row['nilai_kelompok'] - 10, 'alasan' => '']); ok(empty($r['ok']), 'alasan wajib bila beda dari nilai kelompok');
$r = $dosen->req('POST', "$D/individu/$uid", ['nilai' => (float)$row['nilai_kelompok'] - 10, 'alasan' => 'Tidak aktif pada fase uji silang.']); ok(($r['ok'] ?? false) && abs($r['selisih'] + 10) < 0.01, 'penyesuaian individu tersimpan (selisih -10)');
$r = $dosen->req('POST', "$D/individu/$uid", ['nilai' => 101, 'alasan' => 'x']); ok(empty($r['ok']), 'nilai individu > 100 ditolak');
[$st, $csv] = $dosen->req('GET', "/dosen/sesi/$sid/export.csv?level=individu", null, false);
ok($st === 200 && substr_count($csv, "
") >= $N + 1 && str_contains($csv, 'Tidak aktif pada fase uji silang.'), 'ekspor CSV per mahasiswa memuat alasan');
[$st, $csv] = $dosen->req('GET', "/dosen/sesi/$sid/export.csv", null, false); ok($st === 200 && str_contains($csv, 'Skor Analisis'), 'ekspor CSV nilai kelompok');
[$st] = $mhs[1]->req('GET', "/dosen/sesi/$sid/export.csv", null, false); ok($st === 403 || $st === 302, "mahasiswa tidak dapat mengunduh CSV (status $st)");
[$st, $tr] = $dosen->req('GET', "/dosen/sesi/$sid/transkrip", null, false); ok($st === 200 && str_contains($tr, 'Joint Resolution'), 'halaman transkrip dosen');

echo "== Publikasi nilai ke mahasiswa ==
";
$st = $mhs[1]->req('GET', "$A/state"); ok(empty($st['nilai']) && ($st['sesi']['nilai_publik'] ?? true) === false, 'sebelum dipublikasikan: mahasiswa tidak menerima nilai apa pun');
$r = $mhs[1]->req('POST', "$D/publikasi", ['on' => true]); ok(($r['_s'] ?? 0) === 403, 'mahasiswa tidak dapat mempublikasikan nilai');
$r = $dosen->req('POST', "$D/publikasi", ['on' => true]); ok($r['ok'] ?? false, 'dosen mempublikasikan nilai');
$st = $mhs[1]->req('GET', "$A/state");
$n = $st['nilai'] ?? null;
ok($n && $n['peran'] === $peran[1] && isset($n['kelompok']['total']) && isset($n['individu']['nilai']), 'setelah publikasi: mahasiswa melihat nilai kelompok perannya + nilai individunya');
$json = json_encode($st);
ok(!str_contains($json, 'alasan') && !str_contains($json, 'Tidak aktif pada fase uji silang'), 'alasan penyesuaian individual TIDAK terkirim ke mahasiswa');
ok(!isset($st['skor_ai']) && !str_contains($json, 'stub-heuristik') && !str_contains($json, 'rasional'), 'skor AI & rasional tidak terkirim ke mahasiswa');
$adj = $dosen->req('GET', "$D/individu"); $adjRow = null; foreach ($adj['rows'] as $rw) { if ((int)$rw['user_id'] === (int)$uid) { $adjRow = $rw; } }
$adjIdx = null; foreach ($mhs as $i => $c) { $x = $c->req('GET', "$A/state"); if (($x['me']['user_id'] ?? 0) === (int)$uid) { $adjIdx = $i; $adjState = $x; break; } }
ok($adjIdx !== null && abs(($adjState['nilai']['individu']['selisih'] ?? 0) + 10) < 0.01, 'mahasiswa yang disesuaikan melihat nilai individunya dengan selisih -10 (tanpa alasan)');
$r = $dosen->req('POST', "$D/publikasi", ['on' => false]); $st = $mhs[1]->req('GET', "$A/state");
ok(($r['ok'] ?? false) && empty($st['nilai']), 'nilai dapat ditarik kembali (hilang dari state mahasiswa)');
$dosen->req('POST', "$D/publikasi", ['on' => true]);

printf("
Selesai dalam %.1f dtk — lulus: %d, gagal: %d
", microtime(true) - $t0, $pass, $fail);
exit($fail ? 1 : 0);
