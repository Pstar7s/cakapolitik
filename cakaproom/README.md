# CAKAPROOM — Pilot 1 Kelas

Ruang debat digital berbasis kasus untuk mahasiswa, modul **terpisah** dari `cakapolitik_ub` Fase 1.

| Peran | Email |
|---|---|
| admin | `admin@cakaproom.local` |
| dosen | `dosen@cakaproom.local` (pengampu "Kelas Demo PWK-A") |
| mahasiswa | `mhs01@student.cakaproom.local` … `mhs70@…` (70 orang, kelas yang sama) |

Konfigurasi: `config/config.php`; override/rahasia di `config/config.local.php` (lihat `.example`).
Semua asumsi A1–A9 (durasi fase, rentang kata, jumlah delegasi, batas karakter, dst.) ada di blok `sesi` dan dapat diubah.

## Alur pemakaian

1. **Admin**: buat kelas → impor roster CSV (`nama,email,nim`) → (kasus awal sudah terisi, ubah di *Kasus*).
2. **Dosen**: *Sesi baru* (pilih kelas + kasus, durasi fase boleh diubah) → mahasiswa masuk lobby →
   **Bagi Peran** → **Mulai** → **Fase berikutnya** (Argumen → Uji Silang → Negosiasi & Joint Resolution → Penutupan) → **Akhiri sesi**.
3. **Penilaian**: tab *Penilaian* → **Hitung Skor Awal AI** → override per indikator bila perlu → **Tetapkan nilai final** per kelompok →
   tab *Nilai Individual* untuk penyesuaian per mahasiswa (alasan wajib bila berbeda) → ekspor CSV →
   **Publikasikan nilai ke mahasiswa** (tombol di tab *Penilaian*; baru aktif setelah keempat kelompok ditetapkan, dapat ditarik kembali). Mahasiswa melihat tab *Nilai Saya*:
   nilai kelompok perannya (per indikator + catatan dosen) dan nilai individunya sendiri. Skor AI, nilai peran lain, nilai teman, dan alasan penyesuaian individual TIDAK ditampilkan.