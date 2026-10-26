<?php
/**
 * Kasus awal: "Transisi Energi Bersih vs Nasib Tambang Daerah" (Kabupaten Bara Sentosa, FIKTIF).
 * Sumber: "Contoh Paket Kasus CAKAPROOM" (draf). Angka ilustrasi; rujukan hukum belum
 * diverifikasi dosen; rubrik berstatus SEMENTARA sampai disetujui dosen.
 * Dipakai oleh bin/migrate.php (PDO $pdo tersedia).
 */

/** @var PDO $pdo */

$tujuan = "1. Menyusun argumen berbasis data dan hukum dari sudut pandang peran yang ditugaskan.\n2. Bernegosiasi lintas kepentingan tanpa melepas prinsip peran.\n3. Mengaitkan keputusan kebijakan dengan nilai kebangsaan dan etika politik.";

$latar = <<<'TXT'
Kabupaten Bara Sentosa (fiktif) menggantungkan hidupnya pada batu bara selama lebih dari 40 tahun, dan kini harus memutuskan seberapa cepat meninggalkannya.

Tambang di kabupaten ini dikelola PT Bara Nusantara Energi (fiktif), yang juga mengoperasikan pembangkit listrik tenaga uap (PLTU) untuk kebutuhan tambangnya. Perusahaan, pemasok, dan warung-warung di sekitarnya menjadi rantai penghidupan bagi puluhan ribu keluarga. Pemerintah kabupaten juga menerima sebagian besar penerimaan daerahnya dari sektor ini.

Tahun ini tiga hal terjadi hampir bersamaan:
• Pemerintah pusat menegaskan target penurunan emisi dan meminta daerah menyusun peta jalan penghentian bertahap pembangkit batu bara.
• Sebuah konsorsium investor menawarkan pembangunan pembangkit tenaga surya dan pabrik komponen, dengan syarat ada kepastian jadwal dan perizinan.
• Puskesmas di enam kecamatan sekitar tambang melaporkan kasus infeksi saluran pernapasan jauh di atas kecamatan lain, sementara ribuan hektare lahan bekas tambang belum dipulihkan.

Bupati menggelar Musyawarah Transisi Bara Sentosa dan mengundang empat pihak: pemerintah daerah bersama perwakilan kementerian, koalisi masyarakat sipil, perusahaan tambang bersama calon investor, serta perwakilan warga terdampak. Serikat pekerja mengancam mogok jika terjadi pemutusan hubungan kerja massal. Koalisi lingkungan mengancam menggugat jika masa operasi diperpanjang tanpa pemulihan lahan.

Waktu musyawarah terbatas, dan keempat pihak harus menyepakati satu peta jalan 2026–2040 sebelum sidang ditutup. Tanpa kesepakatan, pemerintah pusat akan menetapkan jadwal secara sepihak.
TXT;

$referensi = <<<'TXT'
CATATAN: semua angka di bawah ini FIKTIF (ilustrasi). Ganti dengan hasil survei Indopol atau data nyata sebelum dipakai di kelas.

PROFIL KABUPATEN BARA SENTOSA (ilustrasi)
• Jumlah penduduk: 420.000 jiwa
• Pekerja langsung tambang dan PLTU: 9.800 orang
• Pekerja tidak langsung (angkutan, warung, jasa): ±26.000 orang
• Sumbangan tambang terhadap PDRB: 52%
• Porsi penerimaan daerah yang berasal dari tambang: 58%
• Sisa umur cadangan pada laju produksi saat ini: ±14 tahun
• Lahan bekas tambang belum direklamasi: 3.200 ha
• Kasus ISPA per 1.000 penduduk per tahun, 6 kecamatan sekitar tambang: 148
• Kasus ISPA per 1.000 penduduk per tahun, kecamatan lain: 61
• Tawaran investasi energi surya dan pabrik komponen: Rp 6,5 triliun; ±3.100 pekerjaan langsung
• Kebutuhan dana reklamasi, pelatihan ulang, dan jaminan transisi (10 tahun): Rp 2,4 triliun

SURVEI PERSEPSI WARGA (ilustrasi, n = 1.200) — sikap terhadap jadwal transisi
Kelompok responden | Transisi cepat (≤10 th) | Transisi bertahap (10–20 th) | Menolak / bertahan
• Pekerja tambang dan keluarga | 12% | 41% | 47%
• Pelaku UMKM penunjang | 15% | 46% | 39%
• Warga kecamatan sekitar tambang (non-pekerja) | 38% | 44% | 18%
• Warga kecamatan lain | 33% | 49% | 18%
Hanya 31% responden yang percaya pemerintah daerah mampu mengelola dana transisi secara transparan (ilustrasi).

RUJUKAN HUKUM DAN NILAI YANG BOLEH DIKUTIP (ditulis dari pengetahuan umum; BELUM diverifikasi dosen)
• UUD 1945 Pasal 33 ayat (3) dan (4): kekayaan alam untuk kemakmuran rakyat; perekonomian yang berkelanjutan dan berwawasan lingkungan.
• UUD 1945 Pasal 28H ayat (1): hak atas lingkungan hidup yang baik dan sehat.
• UUD 1945 Pasal 27 ayat (2): hak atas pekerjaan dan penghidupan yang layak.
• UU No. 32 Tahun 2009 tentang Perlindungan dan Pengelolaan Lingkungan Hidup.
• UU Pertambangan Mineral dan Batubara (UU No. 3 Tahun 2020 atas perubahan UU No. 4 Tahun 2009), termasuk kewajiban reklamasi dan pascatambang.
• Pancasila, terutama sila ke-2 (kemanusiaan), ke-3 (persatuan), ke-4 (permusyawaratan), dan ke-5 (keadilan sosial).
TXT;

$kunci = 'Bagaimana Bara Sentosa mencapai energi bersih tanpa mengorbankan pekerja dan warga? Hasilkan satu Peta Jalan Transisi Bara Sentosa 2026–2040 (Joint Resolution) yang menjawab lima klausul.';

$briefs = [
'PEMERINTAH' => <<<'TXT'
Pemerintah (Bupati, Bappeda, perwakilan kementerian)

Kepentingan utama: menjaga keuangan daerah tetap sehat, menaati target nasional, dan memberi kepastian hukum bagi semua pihak.

Tuntutan wajib:
1. Jadwal transisi terukur yang selaras dengan target nasional.
2. Sumber pengganti penerimaan daerah, karena 58% penerimaan kini bergantung pada tambang.
3. Setiap klausul punya dasar hukum yang jelas.

Garis merah: tidak menyetujui keputusan yang menimbulkan defisit mendadak atau melanggar peraturan yang lebih tinggi.

Bahan argumen: 58% penerimaan daerah dari tambang; cadangan tinggal ±14 tahun sehingga penerimaan akan menurun dengan sendirinya; tawaran investasi Rp 6,5 triliun; hanya 31% warga percaya pada pengelolaan dana daerah.

Bisa dikompromikan: tahapan jadwal, pengelola dana transisi (tidak harus pemda), dan forum pengawasan yang terbuka.

Pertanyaan untuk disiapkan: kepada Swasta, "Berapa dana yang berani dikomitmenkan, dan dengan jaminan apa?"; kepada Sipil, "Bagaimana menjaga layanan publik jika penerimaan turun sebelum investasi baru berjalan?"

Nilai yang relevan: sila ke-3 (persatuan, keselarasan pusat dan daerah), sila ke-5, kepastian hukum, dan akuntabilitas.
TXT,
'SIPIL' => <<<'TXT'
Sipil (koalisi LSM lingkungan, advokat, kelompok hak asasi)

Kepentingan utama: pemulihan lingkungan, kesehatan warga, partisipasi yang bermakna, dan keterbukaan data.

Tuntutan wajib:
1. Reklamasi 3.200 ha dengan jaminan dana dan audit independen.
2. Standar emisi dan layanan kesehatan di 6 kecamatan terdampak, dengan data dibuka ke publik.
3. Perwakilan sipil dan warga duduk di forum pengawasan dengan hak akses data.

Garis merah: tidak menerima perpanjangan operasi tanpa pemulihan lahan, dan tidak menerima proses keputusan yang tertutup.

Bahan argumen: ISPA 148 vs 61 per 1.000 penduduk; Pasal 28H ayat (1) UUD 1945; UU No. 32 Tahun 2009; penundaan hanya memindahkan beban ke generasi berikutnya.

Bisa dikompromikan: jadwal bertahap, asal ada tonggak terukur dan sanksi; perusahaan ikut mendanai.

Pertanyaan untuk disiapkan: kepada Swasta, "Bersediakah perusahaan menyetor dana reklamasi ke rekening jaminan sebelum produksi tahap berikutnya?"; kepada Warga, "Jaminan apa yang paling Anda butuhkan agar bisa mendukung penurunan produksi?"

Nilai yang relevan: sila ke-2, sila ke-5, keadilan antargenerasi, dan etika transparansi.
TXT,
'SWASTA' => <<<'TXT'
Swasta (PT Bara Nusantara Energi dan konsorsium investor energi surya)

Kepentingan utama: kelangsungan usaha, pengembalian modal, kepastian perizinan, dan reputasi.

Tuntutan wajib:
1. Jadwal yang memberi waktu cukup untuk mengembalikan investasi yang sudah ditanam.
2. Kepastian dan percepatan izin untuk proyek energi surya.
3. Pembagian biaya yang proporsional, tidak dibebankan seluruhnya kepada perusahaan.

Garis merah: tidak menanggung seluruh Rp 2,4 triliun sendirian, dan tidak menerima penutupan mendadak tanpa kompensasi.

Bahan argumen: 9.800 pekerja langsung dan ±26.000 pekerja tidak langsung bergantung pada tambang; tambang menyumbang 52% PDRB; tawaran investasi Rp 6,5 triliun dengan ±3.100 pekerjaan; Pasal 33 ayat (4) UUD 1945 tentang efisiensi berkeadilan dan keberlanjutan.

Bisa dikompromikan: ikut mendanai sebagian reklamasi dan pelatihan jika jadwal dan insentif jelas; memprioritaskan pekerja lokal; membuka data emisi.

Pertanyaan untuk disiapkan: kepada Pemerintah, "Insentif dan kepastian izin apa yang bisa dijamin tertulis?"; kepada Warga, "Keterampilan apa yang paling dibutuhkan agar pekerja bisa masuk proyek baru?"

Nilai yang relevan: sila ke-5, tanggung jawab sosial perusahaan, dan etika bisnis.
TXT,
'WARGA' => <<<'TXT'
Warga (perwakilan pekerja tambang, pelaku UMKM, penduduk sekitar tambang)

Catatan: warga bukan satu suara. Pekerja yang takut kehilangan penghasilan dan penduduk yang sakit akibat polusi bisa berbeda sikap; delegasi harus mengakomodasi keduanya.

Kepentingan utama: penghasilan yang aman, kesehatan keluarga, dan didengar dalam keputusan.

Tuntutan wajib:
1. Jaminan penghidupan (pelatihan, pekerjaan baru, atau tunjangan masa transisi) sebelum produksi dikurangi.
2. Layanan kesehatan dan perbaikan lingkungan segera di kecamatan terdampak.
3. Kursi warga di forum pengawasan.

Garis merah: tidak menerima PHK massal tanpa jaminan, dan tidak menerima keputusan yang diambil tanpa perwakilan warga.

Bahan argumen: 47% pekerja tambang menolak transisi, sedangkan 38% warga sekitar tambang mendukung transisi cepat; ±26.000 pekerja tidak langsung; ISPA 148 vs 61; hanya 31% percaya pada pengelolaan dana.

Bisa dikompromikan: menerima transisi bertahap bila jaminan nyata, tertulis, dan terukur.

Pertanyaan untuk disiapkan: kepada Pemerintah, "Siapa yang menjamin dana transisi sampai ke tangan pekerja dan UMKM?"; kepada Swasta, "Berapa pekerja lokal yang dijamin terserap di proyek baru, dan kapan?"

Nilai yang relevan: sila ke-2, sila ke-4 (musyawarah), sila ke-5, dan Pasal 27 ayat (2) UUD 1945.
TXT,
];

$klausul = [
    ['Jadwal transisi', 'Tahun target pengurangan produksi dan penghentian PLTU, beserta syarat tiap tahap'],
    ['Perlindungan pekerja dan penghidupan', 'Pelatihan ulang, prioritas kerja di proyek baru, jaminan penghasilan selama masa transisi'],
    ['Pemulihan lingkungan dan kesehatan', 'Jadwal reklamasi 3.200 ha, standar emisi, layanan kesehatan di 6 kecamatan terdampak'],
    ['Pembiayaan', 'Pembagian beban Rp 2,4 triliun antara pemerintah, perusahaan, dan investor; siapa yang mengelola dananya'],
    ['Pengawasan dan akuntabilitas', 'Forum multipihak, indikator capaian, keterbukaan data, dan sanksi bila target meleset'],
];

$rubrik = [
    'catatan' => 'Skor total = 0,35 x A + 0,35 x B + 0,30 x C (dihitung sistem). Rubrik DRAF dari paket kasus contoh; belum disetujui dosen.',
    'indikator' => [
        ['kode' => 'A', 'nama' => 'Kedalaman Analisis Data dan Hukum', 'bobot' => 35, 'level' => [
            ['nama' => 'Sangat Baik', 'rentang' => '85-100', 'deskriptor' => 'Hampir semua argumen memakai data kasus dengan benar dan memuat minimal satu rujukan hukum atau konstitusi yang relevan. Hubungan data ke klaim jelas, keterbatasan data dikenali, dan antaranggota konsisten.'],
            ['nama' => 'Baik', 'rentang' => '70-84', 'deskriptor' => 'Mayoritas argumen berbasis data dan hukum yang benar. Beberapa klaim kurang didukung atau rujukan hukumnya umum, tetapi penalaran tetap jelas.'],
            ['nama' => 'Cukup', 'rentang' => '55-69', 'deskriptor' => 'Data atau hukum hanya disebut tanpa dijelaskan hubungannya dengan klaim, atau terdapat ketidaktepatan kecil.'],
            ['nama' => 'Kurang', 'rentang' => '<55', 'deskriptor' => 'Argumen umumnya opini tanpa data atau hukum, atau data salah kutip dan keliru ditafsirkan.'],
        ]],
        ['kode' => 'B', 'nama' => 'Negosiasi dan Empati Peran', 'bobot' => 35, 'level' => [
            ['nama' => 'Sangat Baik', 'rentang' => '85-100', 'deskriptor' => 'Menjawab uji silang langsung dan substantif. Mengakui kepentingan sah peran lain, menawarkan opsi imbal balik yang konkret, dan klausul akhir mencerminkan keseimbangan kepentingan.'],
            ['nama' => 'Baik', 'rentang' => '70-84', 'deskriptor' => 'Menjawab uji silang dengan baik meski sesekali berkelit. Ada usulan kompromi, tetapi imbal baliknya kurang konkret.'],
            ['nama' => 'Cukup', 'rentang' => '55-69', 'deskriptor' => 'Cenderung mengulang posisi, pengakuan terhadap kepentingan lain minim, dan kompromi muncul pasif.'],
            ['nama' => 'Kurang', 'rentang' => '<55', 'deskriptor' => 'Mengabaikan atau menyerang peran lain, tidak menjawab pertanyaan, keluar dari peran, atau tidak berkontribusi pada kesepakatan.'],
        ]],
        ['kode' => 'C', 'nama' => 'Nilai Kebangsaan dan Etika Politik', 'bobot' => 30, 'level' => [
            ['nama' => 'Sangat Baik', 'rentang' => '85-100', 'deskriptor' => 'Hampir setiap argumen mengaitkan nilai Pancasila atau konstitusi secara spesifik dan beralasan, bukan slogan. Etika berdebat terjaga, dan kepentingan publik serta keadilan dipertimbangkan, termasuk bagi pihak yang tidak hadir.'],
            ['nama' => 'Baik', 'rentang' => '70-84', 'deskriptor' => 'Kaitan nilai ada dan benar, tetapi kadang umum. Etika berdebat terjaga.'],
            ['nama' => 'Cukup', 'rentang' => '55-69', 'deskriptor' => 'Nilai disebut sebagai slogan tanpa penjelasan. Sesekali ada generalisasi atau nada kurang etis.'],
            ['nama' => 'Kurang', 'rentang' => '<55', 'deskriptor' => 'Tidak ada kaitan nilai, nilai dipakai keliru atau manipulatif, atau ada serangan personal, hoaks, atau SARA.'],
        ]],
    ],
    'kalibrasi' => 'Contoh argumen kuat (Sipil, reklamasi 3.200 ha dengan dana jaminan dan audit independen; memakai data ISPA 148 vs 61, UU Minerba, Pasal 28H ayat 1, sila ke-5) setara skor 85-90 pada A dan C. Contoh argumen lemah (opini umum, tanpa data/hukum, nilai hanya slogan, menuding pihak lain) setara 35-45.',
];

$pdo->prepare('INSERT INTO cr_kasus (judul, mata_kuliah, tujuan, latar_belakang, referensi_data, pertanyaan_kunci, rubrik_json, rubrik_status, status) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([
        'Dilema Transisi Energi Bersih vs Nasib Industri Tambang Daerah',
        'Pendidikan Wawasan Kebangsaan / Kewarganegaraan (sesuaikan)',
        $tujuan, $latar, $referensi, $kunci,
        json_encode($rubrik, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'sementara', 'siap',
    ]);
$kasusId = (int)$pdo->lastInsertId();

foreach ($briefs as $kode => $teks) {
    $pdo->prepare('INSERT INTO cr_kasus_peran (kasus_id, kode_peran, brief_peran) VALUES (?,?,?)')->execute([$kasusId, $kode, $teks]);
}
foreach ($klausul as $i => [$judul, $desk]) {
    $pdo->prepare('INSERT INTO cr_kasus_klausul (kasus_id, no, judul, deskripsi) VALUES (?,?,?,?)')->execute([$kasusId, $i + 1, $judul, $desk]);
}
echo "  kasus awal 'Bara Sentosa' (id {$kasusId}) dimuat.\n";
