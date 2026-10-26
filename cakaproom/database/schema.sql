SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS cr_audit_log, cr_api_log, cr_nilai_individu, cr_nilai_final, cr_skor_ai,
  cr_resolusi_sikap, cr_resolusi_klausul, cr_resolusi, cr_post, cr_peserta_sesi, cr_sesi,
  cr_kasus_klausul, cr_kasus_peran, cr_kasus, cr_kelas_mahasiswa, cr_kelas, cr_otp, cr_users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE cr_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role ENUM('admin','dosen','mahasiswa') NOT NULL,
  nama VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  nim VARCHAR(32) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  privasi_ok_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email),
  KEY idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_otp (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kode_otp VARCHAR(12) NOT NULL,
  expired_at DATETIME NOT NULL,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id, id),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES cr_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_kelas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kelas VARCHAR(120) NOT NULL,
  mata_kuliah VARCHAR(160) NOT NULL DEFAULT '',
  dosen_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_kelas_dosen FOREIGN KEY (dosen_id) REFERENCES cr_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_kelas_mahasiswa (
  kelas_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (kelas_id, user_id),
  CONSTRAINT fk_km_kelas FOREIGN KEY (kelas_id) REFERENCES cr_kelas(id) ON DELETE CASCADE,
  CONSTRAINT fk_km_user FOREIGN KEY (user_id) REFERENCES cr_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_kasus (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(200) NOT NULL,
  mata_kuliah VARCHAR(160) NOT NULL DEFAULT '',
  tujuan TEXT NULL,
  latar_belakang MEDIUMTEXT NOT NULL,
  referensi_data MEDIUMTEXT NULL,
  pertanyaan_kunci TEXT NULL,
  rubrik_json MEDIUMTEXT NULL,
  rubrik_status ENUM('sementara','disetujui') NOT NULL DEFAULT 'sementara',
  status ENUM('draft','siap') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_kasus_peran (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kasus_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NOT NULL,
  brief_peran MEDIUMTEXT NOT NULL,
  UNIQUE KEY uq_kasus_peran (kasus_id, kode_peran),
  CONSTRAINT fk_kp_kasus FOREIGN KEY (kasus_id) REFERENCES cr_kasus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_kasus_klausul (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kasus_id INT UNSIGNED NOT NULL,
  no TINYINT UNSIGNED NOT NULL,
  judul VARCHAR(160) NOT NULL,
  deskripsi TEXT NULL,
  UNIQUE KEY uq_kasus_no (kasus_id, no),
  CONSTRAINT fk_kk_kasus FOREIGN KEY (kasus_id) REFERENCES cr_kasus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_sesi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT UNSIGNED NOT NULL,
  kasus_id INT UNSIGNED NOT NULL,
  judul VARCHAR(200) NOT NULL DEFAULT '',
  status ENUM('draft','lobby','berjalan','selesai','dinilai') NOT NULL DEFAULT 'lobby',
  fase_aktif ENUM('lobby','argumen','uji_silang','negosiasi','penutupan') NOT NULL DEFAULT 'lobby',
  fase_mulai_at DATETIME NULL,
  fase_berakhir_at DATETIME NULL,
  jeda_sisa INT NULL,
  durasi_fase TEXT NOT NULL,
  word_min SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  word_max SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  peran_dibagi TINYINT(1) NOT NULL DEFAULT 0,
  rev INT UNSIGNED NOT NULL DEFAULT 1,
  res_rev INT UNSIGNED NOT NULL DEFAULT 0,
  nilai_publik TINYINT(1) NOT NULL DEFAULT 0,
  nilai_publik_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  KEY idx_kelas (kelas_id),
  CONSTRAINT fk_sesi_kelas FOREIGN KEY (kelas_id) REFERENCES cr_kelas(id),
  CONSTRAINT fk_sesi_kasus FOREIGN KEY (kasus_id) REFERENCES cr_kasus(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_peserta_sesi (
  sesi_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NULL,
  is_delegasi TINYINT(1) NOT NULL DEFAULT 0,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen DATETIME NULL,
  PRIMARY KEY (sesi_id, user_id),
  KEY idx_peran (sesi_id, kode_peran),
  CONSTRAINT fk_ps_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES cr_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_post (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesi_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NOT NULL,
  tipe ENUM('argumen','pertanyaan','jawaban','negosiasi') NOT NULL,
  parent_id INT UNSIGNED NULL,
  target_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NULL,
  claim TEXT NULL,
  evidence TEXT NULL,
  nilai_kebangsaan TEXT NULL,
  isi TEXT NULL,
  word_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  client_key VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_idem (sesi_id, user_id, client_key),
  KEY idx_since (sesi_id, id),
  KEY idx_tipe (sesi_id, tipe, user_id),
  CONSTRAINT fk_post_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_user FOREIGN KEY (user_id) REFERENCES cr_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Joint Resolution. Editor kolaboratif Yjs ditunda (butuh keputusan Jalur A/B,
-- lihat brief 6.3); pilot memakai fallback "kunci per klausul".
CREATE TABLE cr_resolusi (
  sesi_id INT UNSIGNED PRIMARY KEY,
  yjs_state LONGBLOB NULL,
  teks_final LONGTEXT NULL,
  snapshot_at DATETIME NULL,
  final_by INT UNSIGNED NULL,
  CONSTRAINT fk_res_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_resolusi_klausul (
  sesi_id INT UNSIGNED NOT NULL,
  no TINYINT UNSIGNED NOT NULL,
  teks MEDIUMTEXT NULL,
  rev INT UNSIGNED NOT NULL DEFAULT 0,
  editor_id INT UNSIGNED NULL,
  locked_by INT UNSIGNED NULL,
  lock_until DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (sesi_id, no),
  CONSTRAINT fk_rk_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_resolusi_sikap (
  sesi_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NOT NULL,
  sikap ENUM('setuju','keberatan') NOT NULL,
  catatan TEXT NULL,
  user_id INT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (sesi_id, kode_peran),
  CONSTRAINT fk_rs_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_skor_ai (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesi_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NOT NULL,
  model VARCHAR(80) NOT NULL,
  versi_prompt VARCHAR(40) NOT NULL,
  skor_analisis DECIMAL(5,2) NULL,
  skor_negosiasi DECIMAL(5,2) NULL,
  skor_nilai DECIMAL(5,2) NULL,
  rasional_json MEDIUMTEXT NULL,
  skor_total_ai DECIMAL(5,2) NULL,
  bukti_terbatas TINYINT(1) NOT NULL DEFAULT 0,
  raw_json MEDIUMTEXT NULL,
  input_tokens INT UNSIGNED NULL,
  output_tokens INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sesi (sesi_id, kode_peran, id),
  CONSTRAINT fk_skor_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nilai KELOMPOK (satu baris per kelompok per sesi).
CREATE TABLE cr_nilai_final (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesi_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NOT NULL,
  skor_analisis DECIMAL(5,2) NOT NULL,
  skor_negosiasi DECIMAL(5,2) NOT NULL,
  skor_nilai DECIMAL(5,2) NOT NULL,
  skor_total DECIMAL(5,2) NOT NULL,
  catatan_dosen TEXT NULL,
  ditetapkan_oleh INT UNSIGNED NOT NULL,
  ditetapkan_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sumber ENUM('ai','override') NOT NULL,
  UNIQUE KEY uq_sesi_peran (sesi_id, kode_peran),
  CONSTRAINT fk_nf_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nilai INDIVIDUAL (satu baris per mahasiswa per sesi) - keputusan #13.
CREATE TABLE cr_nilai_individu (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesi_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  kode_peran ENUM('PEMERINTAH','SIPIL','SWASTA','WARGA') NOT NULL,
  nilai_kelompok DECIMAL(5,2) NOT NULL,
  nilai_individu DECIMAL(5,2) NOT NULL,
  selisih DECIMAL(5,2) NOT NULL DEFAULT 0,
  alasan TEXT NULL,
  ditetapkan_oleh INT UNSIGNED NOT NULL,
  ditetapkan_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sesi_user (sesi_id, user_id),
  CONSTRAINT fk_ni_sesi FOREIGN KEY (sesi_id) REFERENCES cr_sesi(id) ON DELETE CASCADE,
  CONSTRAINT fk_ni_user FOREIGN KEY (user_id) REFERENCES cr_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_api_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesi_id INT UNSIGNED NULL,
  konteks VARCHAR(40) NOT NULL,
  model VARCHAR(80) NOT NULL,
  ok TINYINT(1) NOT NULL DEFAULT 1,
  input_tokens INT UNSIGNED NULL,
  output_tokens INT UNSIGNED NULL,
  latency_ms INT UNSIGNED NULL,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cr_audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  aksi VARCHAR(60) NOT NULL,
  detail TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aksi (aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
