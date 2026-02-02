-- =========================================================
-- WhisperedFrames – FINAL DATABASE SCHEMA
-- MySQL 8.x / utf8mb4
-- 01-02-2026 v2
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS whisperedframes
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE whisperedframes;

-- =========================================================
-- USERS
-- =========================================================
CREATE TABLE users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,

  first_name    VARCHAR(100),
  last_name     VARCHAR(100),
  phone         VARCHAR(50),
  messenger     VARCHAR(150),
  contact_notes TEXT,

  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- ROLES
-- =========================================================
CREATE TABLE roles (
  id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id)
    REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (name) VALUES ('admin'), ('client');

-- =========================================================
-- ALBUMS
-- =========================================================
CREATE TABLE albums (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(40) NOT NULL UNIQUE,
  title       VARCHAR(200),
  album_comment TEXT NOT NULL,

  created_by  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,

  CONSTRAINT fk_albums_user FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_album_access (
  user_id     BIGINT UNSIGNED NOT NULL,
  album_id    BIGINT UNSIGNED NOT NULL,
  access_role ENUM('owner','viewer') NOT NULL DEFAULT 'viewer',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (user_id, album_id),
  KEY idx_user_album_album (album_id),

  CONSTRAINT fk_uaa_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uaa_album FOREIGN KEY (album_id)
    REFERENCES albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PHOTOS
-- =========================================================
CREATE TABLE photos (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id              BIGINT UNSIGNED NOT NULL,

  sort_order            INT NOT NULL DEFAULT 0,
  is_visible            TINYINT(1) NOT NULL DEFAULT 1,

  client_rating         TINYINT NULL,
  admin_rating          TINYINT NULL,

  client_selected_at    DATETIME NULL,
  admin_ready_at        DATETIME NULL,
  download_allowed_at   DATETIME NULL,

  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_photos_album (album_id),
  KEY idx_photos_rating (client_rating),
  KEY idx_photos_admin_rating (admin_rating),

  CONSTRAINT fk_photos_album FOREIGN KEY (album_id)
    REFERENCES albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PHOTO FILES
-- =========================================================
CREATE TABLE photo_files (
  id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photo_id BIGINT UNSIGNED NOT NULL,

  kind     ENUM('original_jpg','preview_800','thumb') NOT NULL,
  path     VARCHAR(500) NOT NULL,

  file_size_bytes BIGINT UNSIGNED,
  file_mtime      DATETIME,

  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_photo_kind (photo_id, kind),

  CONSTRAINT fk_photo_files_photo FOREIGN KEY (photo_id)
    REFERENCES photos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- PHOTO COMMENTS / CHAT
-- =========================================================
CREATE TABLE photo_comments (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photo_id      BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,

  comment_text  TEXT NOT NULL,
  is_internal   TINYINT(1) NOT NULL DEFAULT 0,
  is_admin_note TINYINT(1) NOT NULL DEFAULT 0,

  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_pc_photo (photo_id),

  CONSTRAINT fk_pc_photo FOREIGN KEY (photo_id)
    REFERENCES photos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- AUDIT LOG
-- =========================================================
CREATE TABLE audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  user_id    BIGINT UNSIGNED,
  album_id   BIGINT UNSIGNED,
  photo_id   BIGINT UNSIGNED,

  event      VARCHAR(80) NOT NULL,
  meta_json  JSON,
  ip         VARCHAR(45),

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_audit_user_time (user_id, created_at),

  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_album FOREIGN KEY (album_id)
    REFERENCES albums(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_photo FOREIGN KEY (photo_id)
    REFERENCES photos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- SEED: ADMIN USER
-- =========================================================
INSERT INTO users
  (id, email, password_hash, is_active, first_name, last_name, created_at)
VALUES
  (3, 'admin@wf.local',
   '$2y$10$Tozteg1rfAQlaQkOPSn.kevWF0/A7yXQ2Y3qG6ldDpXIgB3NjjrXu',
   1, 'Admin', 'Glowny', NOW())
ON DUPLICATE KEY UPDATE email = email;

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT 3, id FROM roles WHERE name = 'admin';
-- =======================================
-- 1) Tabela sekcji w albumie (sub-albumy / stylizacje)
CREATE TABLE IF NOT EXISTS album_sections (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id   BIGINT UNSIGNED NOT NULL,
  title      VARCHAR(200) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_sections_album (album_id),
  KEY idx_sections_album_sort (album_id, sort_order),

  CONSTRAINT fk_sections_album
    FOREIGN KEY (album_id) REFERENCES albums(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Kolumna photos.section_id (jeśli jej nie masz)
--    MySQL nie ma "ADD COLUMN IF NOT EXISTS" wprost, więc jeśli to pole już masz
--    i dostaniesz błąd "Duplicate column", to po prostu pomiń ten krok.
ALTER TABLE photos
  ADD COLUMN section_id BIGINT UNSIGNED NULL AFTER sort_order;

-- 3) Indeks + FK
ALTER TABLE photos
  ADD KEY idx_photos_section (section_id);

ALTER TABLE photos
  ADD CONSTRAINT fk_photos_section
    FOREIGN KEY (section_id) REFERENCES album_sections(id)
    ON DELETE SET NULL;

-- 4) (Opcjonalnie, ale polecam) Utwórz domyślną sekcję "Główne" dla każdego albumu,
--    jeśli album nie ma jeszcze żadnych sekcji.
INSERT INTO album_sections (album_id, title, sort_order)
SELECT a.id, 'Główne', 0
FROM albums a
LEFT JOIN album_sections s ON s.album_id = a.id
WHERE s.id IS NULL;

-- 5) (Opcjonalnie) Przypisz wszystkie istniejące zdjęcia do sekcji "Główne",
--    jeśli nie mają section_id.
UPDATE photos p
JOIN album_sections s
  ON s.album_id = p.album_id AND s.title = 'Główne'
SET p.section_id = s.id
WHERE p.section_id IS NULL;


ALTER TABLE albums
  ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER created_at;
