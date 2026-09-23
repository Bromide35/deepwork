-- ============================================================
--  DeepWork · ฐานข้อมูลฉบับสมบูรณ์
-- ============================================================
DROP DATABASE IF EXISTS deepwork;
CREATE DATABASE deepwork CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE deepwork;

CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL UNIQUE,
  display_name    VARCHAR(80)  NOT NULL,
  avatar          VARCHAR(120) NULL,
  password_hash   VARCHAR(255) NOT NULL,
  timezone        VARCHAR(64)  NOT NULL DEFAULT 'Asia/Bangkok',
  day_start_hour  TINYINT      NOT NULL DEFAULT 4,
  daily_goal_min  SMALLINT     NOT NULL DEFAULT 180,
  zen_mode        TINYINT(1)   NOT NULL DEFAULT 0,
  theme           ENUM('dark','light','auto') NOT NULL DEFAULT 'dark',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(60)  NOT NULL,
  color       CHAR(7)      NOT NULL DEFAULT '#FF7A2F',
  icon        VARCHAR(8)   NOT NULL DEFAULT '📘',
  archived    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_cat_user (user_id, archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sessions (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  category_id       INT UNSIGNED NULL,
  mode              ENUM('stopwatch','countdown','pomodoro') NOT NULL DEFAULT 'stopwatch',
  target_seconds    INT UNSIGNED NULL,
  started_at        DATETIME NOT NULL,
  ended_at          DATETIME NULL,
  last_heartbeat_at DATETIME NULL,
  away_seconds      INT NOT NULL DEFAULT 0,
  blur_count        INT NOT NULL DEFAULT 0,
  focus_score       TINYINT UNSIGNED NULL,
  is_edited         TINYINT(1) NOT NULL DEFAULT 0,
  is_abandoned      TINYINT(1) NOT NULL DEFAULT 0,
  completed         TINYINT(1) NOT NULL DEFAULT 0,
  note              VARCHAR(255) NULL,
  day_bucket        DATE NOT NULL,
  CONSTRAINT fk_ses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ses_cat  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_ses_day  (user_id, day_bucket),
  INDEX idx_ses_open (user_id, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip           VARBINARY(16) NOT NULL,
  email        VARCHAR(190)  NOT NULL,
  attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_att (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;