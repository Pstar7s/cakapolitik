<div class="card-cr p-4 mx-auto" style="max-width:640px">
  <h1 class="h4">Pemberitahuan Penggunaan Data</h1>
  <p>Halo, <strong><?= e($u['nama']) ?></strong>. CAKAPROOM adalah simulasi debat kelas. Agar sesi berjalan, kami memproses data berikut:</p>
  <ul>
    <li><strong>Data akun:</strong> nama, email, dan NIM (bila ada) dari daftar kelas yang diunggah dosen/admin.</li>
    <li><strong>Isi kegiatan sesi:</strong> argumen, pertanyaan, jawaban, pesan negosiasi, dan Joint Resolution — terlihat oleh peserta sesi dan dosen.</li>
    <li><strong>Penilaian:</strong> skor awal AI per kelompok adalah <em>saran</em>; nilai akhir ditetapkan dosen. Teks yang dikirim ke layanan AI telah dianonimkan (nama diganti kode), tanpa email/NIM.</li>
  </ul>
  <p class="small mut">Data dipakai hanya untuk keperluan perkuliahan dan penilaian. Masa simpan transkrip &amp; nilai mengikuti kebijakan yang ditetapkan penyelenggara.</p>
  <form method="post" action="<?= url('/privasi') ?>"><?= csrf_field() ?>
    <button class="btn btn-navy">Saya mengerti &amp; lanjut</button>
  </form>
</div>
