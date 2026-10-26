-- CAKAPOLITIK_UB.ID — Fase 1 (MVP) schema
-- Target: MySQL 8 / MariaDB 10.4+  (utf8mb4)
-- Ref: Technical_Brief_Fase1_MVP_cakapolitik.md §5

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `poll_jawaban`;
DROP TABLE IF EXISTS `poll_opsi`;
DROP TABLE IF EXISTS `poll_pertanyaan`;
DROP TABLE IF EXISTS `pesan`;
DROP TABLE IF EXISTS `percakapan`;
DROP TABLE IF EXISTS `aspirasi`;
DROP TABLE IF EXISTS `persona_knowledge_chunks`;
DROP TABLE IF EXISTS `persona`;
DROP TABLE IF EXISTS `otp_verifikasi`;
DROP TABLE IF EXISTS `warga`;
DROP TABLE IF EXISTS `dapil`;
DROP TABLE IF EXISTS `api_log`;
DROP TABLE IF EXISTS `admin_user`;

-- ---------------------------------------------------------------------------
CREATE TABLE `dapil` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `nama_dapil`       VARCHAR(120) NOT NULL,
  `wilayah_cakupan`  TEXT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `warga` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `nama`            VARCHAR(160) NOT NULL,
  `email`           VARCHAR(190) NOT NULL,
  `alamat_rt`       VARCHAR(12) NULL,
  `alamat_rw`       VARCHAR(12) NULL,
  `provinsi`        VARCHAR(120) NOT NULL DEFAULT 'Jawa Timur',
  `kelurahan`       VARCHAR(120) NULL,
  `kecamatan`       VARCHAR(120) NULL,
  `kab_kota`        VARCHAR(120) NULL,
  `kode_wilayah`    VARCHAR(13) NULL COMMENT 'kode kelurahan/desa (level 4)',
  `dapil_id`        INT NULL,
  `bahasa_pilihan`  VARCHAR(24) NOT NULL DEFAULT 'Indonesia',
  `is_verified`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_warga_email` (`email`),
  KEY `ix_warga_dapil` (`dapil_id`),
  CONSTRAINT `fk_warga_dapil` FOREIGN KEY (`dapil_id`) REFERENCES `dapil`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `otp_verifikasi` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `warga_id`    INT NOT NULL,
  `kode_otp`    VARCHAR(10) NOT NULL,
  `expired_at`  DATETIME NOT NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `attempts`    TINYINT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `ix_otp_warga` (`warga_id`),
  CONSTRAINT `fk_otp_warga` FOREIGN KEY (`warga_id`) REFERENCES `warga`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `persona` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `nama`                VARCHAR(160) NOT NULL,
  `partai`              VARCHAR(120) NULL,
  `jabatan`             VARCHAR(200) NULL,
  `dapil_id`            INT NULL,
  `foto_url`            VARCHAR(500) NULL,
  `deskripsi_singkat`   TEXT NULL,
  `pesan_pembuka`       TEXT NULL COMMENT 'Sapaan pembuka scripted saat warga memilih persona (bukan AI)',
  `gaya_bicara`         TEXT NULL COMMENT 'Instruksi gaya bicara untuk system prompt',
  `system_prompt_extra` TEXT NULL COMMENT 'Instruksi tambahan opsional dari admin',
  `perolehan_suara`     INT NULL,
  `status_aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `ix_persona_dapil` (`dapil_id`),
  CONSTRAINT `fk_persona_dapil` FOREIGN KEY (`dapil_id`) REFERENCES `dapil`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Knowledge base chunks. `embedding` is reserved for Fase 2 (vector search);
-- Fase 1 retrieval is lexical (TF-IDF cosine) computed in PHP.
CREATE TABLE `persona_knowledge_chunks` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `persona_id`  INT NOT NULL,
  `sumber`      VARCHAR(120) NULL COMMENT 'profil | pernyataan | medsos | berita | dll',
  `judul`       VARCHAR(200) NULL,
  `teks_chunk`  TEXT NOT NULL,
  `referensi`   VARCHAR(500) NULL COMMENT 'URL / kutipan sumber',
  `embedding`   JSON NULL,
  `is_aktif`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `ix_chunk_persona` (`persona_id`),
  CONSTRAINT `fk_chunk_persona` FOREIGN KEY (`persona_id`) REFERENCES `persona`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `percakapan` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `warga_id`    INT NULL,
  `persona_id`  INT NULL,
  `session_id`  VARCHAR(120) NOT NULL,
  `mode`        ENUM('brone','persona') NOT NULL DEFAULT 'persona',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `ix_pc_warga` (`warga_id`),
  KEY `ix_pc_persona` (`persona_id`),
  KEY `ix_pc_session` (`session_id`),
  CONSTRAINT `fk_pc_warga` FOREIGN KEY (`warga_id`) REFERENCES `warga`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_persona` FOREIGN KEY (`persona_id`) REFERENCES `persona`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `pesan` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `percakapan_id`  INT NOT NULL,
  `pengirim`       ENUM('warga','ai','brone') NOT NULL,
  `isi_pesan`      MEDIUMTEXT NOT NULL,
  `meta`           JSON NULL COMMENT 'token usage, chunk ids, dll',
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `ix_pesan_pc` (`percakapan_id`),
  CONSTRAINT `fk_pesan_pc` FOREIGN KEY (`percakapan_id`) REFERENCES `percakapan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `aspirasi` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `warga_id`        INT NOT NULL,
  `persona_id`      INT NULL,
  `percakapan_id`   INT NULL,
  `isi_aspirasi`    TEXT NOT NULL,
  `kategori`        ENUM('pendidikan','kesehatan','ekonomi','infrastruktur',
                         'pemerintahan','hukum','lingkungan','sosial-budaya','lainnya')
                    NOT NULL DEFAULT 'lainnya',
  `is_anonim`       TINYINT(1) NOT NULL DEFAULT 0,
  `status_moderasi` ENUM('lolos','ditahan','belum_dicek') NOT NULL DEFAULT 'belum_dicek',
  `alasan_moderasi` TEXT NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `ix_asp_warga` (`warga_id`),
  KEY `ix_asp_persona` (`persona_id`),
  KEY `ix_asp_kategori` (`kategori`),
  KEY `ix_asp_status` (`status_moderasi`),
  CONSTRAINT `fk_asp_warga` FOREIGN KEY (`warga_id`) REFERENCES `warga`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asp_persona` FOREIGN KEY (`persona_id`) REFERENCES `persona`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asp_pc` FOREIGN KEY (`percakapan_id`) REFERENCES `percakapan`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `api_log` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `konteks`       VARCHAR(60) NULL COMMENT 'brone | persona | moderasi | klasifikasi',
  `model`         VARCHAR(80) NULL,
  `input_tokens`  INT NULL,
  `output_tokens` INT NULL,
  `ok`            TINYINT(1) NOT NULL DEFAULT 1,
  `error`         VARCHAR(500) NULL,
  `latency_ms`    INT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `ix_apilog_konteks` (`konteks`),
  KEY `ix_apilog_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `admin_user` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(80) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nama`          VARCHAR(120) NULL,
  `role`          VARCHAR(40) NOT NULL DEFAULT 'admin',
  `last_login_at` DATETIME NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
