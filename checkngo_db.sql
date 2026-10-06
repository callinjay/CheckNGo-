-- =====================================================================
-- CheckNGo: Context-Aware Personal Belongings Pre-Departure Readiness
-- Database Schema
-- Engine: MySQL 8+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS checkngo_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE checkngo_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150)      NOT NULL,
    email           VARCHAR(190)      NOT NULL,
    password_hash   VARCHAR(255)      NOT NULL,
    role            ENUM('student','professional','other') DEFAULT 'student',
    avatar_path     VARCHAR(255)      NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- belongings  (the user's personal item catalog)
-- ---------------------------------------------------------------------
CREATE TABLE belongings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    item_name       VARCHAR(120) NOT NULL,
    category        VARCHAR(60)  NULL,          -- e.g. Electronics, Documents
    description     VARCHAR(255) NULL,
    default_priority ENUM('critical','important','optional') NOT NULL DEFAULT 'important',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,   -- soft delete flag
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_belongings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_belongings_user (user_id),
    UNIQUE KEY uq_belongings_user_item (user_id, item_name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- activities (School, Work, Travel, Personal, Examination, Laboratory)
-- ---------------------------------------------------------------------
CREATE TABLE activities (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    activity_name       VARCHAR(100) NOT NULL,      -- School / Work / Travel ...
    activity_type       ENUM('school','work','travel','personal','examination','laboratory','custom') NOT NULL,
    destination_label   VARCHAR(150) NULL,          -- e.g. "IT Laboratory"
    icon                VARCHAR(50)  NULL,           -- font-awesome class
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_activities_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- checklist_templates
-- ---------------------------------------------------------------------
CREATE TABLE checklist_templates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    activity_id     INT UNSIGNED NOT NULL,
    template_name   VARCHAR(120) NOT NULL,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_templates_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    INDEX idx_templates_user (user_id),
    INDEX idx_templates_activity (activity_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- checklist_template_items
-- ---------------------------------------------------------------------
CREATE TABLE checklist_template_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id     INT UNSIGNED NOT NULL,
    belonging_id    INT UNSIGNED NOT NULL,
    priority        ENUM('critical','important','optional') NOT NULL DEFAULT 'important',
    is_required     TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_tplitems_template FOREIGN KEY (template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_tplitems_belonging FOREIGN KEY (belonging_id) REFERENCES belongings(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tplitem (template_id, belonging_id),
    INDEX idx_tplitems_template (template_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- departure_schedules
-- ---------------------------------------------------------------------
CREATE TABLE departure_schedules (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    activity_id         INT UNSIGNED NOT NULL,
    template_id         INT UNSIGNED NOT NULL,
    departure_date      DATE NOT NULL,
    departure_time      TIME NOT NULL,
    timezone            VARCHAR(60) NOT NULL DEFAULT 'Asia/Manila',
    status              ENUM('upcoming','in_progress','completed','missed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_template FOREIGN KEY (template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE,
    INDEX idx_schedule_user_status (user_id, status),
    INDEX idx_schedule_datetime (departure_date, departure_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- checklist_sessions (one per "run" of a checklist toward a departure)
-- ---------------------------------------------------------------------
CREATE TABLE checklist_sessions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNSIGNED NOT NULL,
    activity_id             INT UNSIGNED NOT NULL,
    template_id             INT UNSIGNED NOT NULL,
    departure_schedule_id   INT UNSIGNED NULL,
    session_date            DATE NOT NULL,
    start_time              DATETIME NULL,
    completion_time         DATETIME NULL,
    readiness_score         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    session_status          ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    departure_confirmed     TINYINT(1) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_template FOREIGN KEY (template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_schedule FOREIGN KEY (departure_schedule_id) REFERENCES departure_schedules(id) ON DELETE SET NULL,
    INDEX idx_session_user_date (user_id, session_date),
    INDEX idx_session_status (session_status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- checklist_session_items (snapshotted so later edits don't rewrite history)
-- ---------------------------------------------------------------------
CREATE TABLE checklist_session_items (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id              INT UNSIGNED NOT NULL,
    belonging_id            INT UNSIGNED NULL,      -- nullable: source item may later be deleted
    item_name_snapshot      VARCHAR(120) NOT NULL,
    priority_snapshot       ENUM('critical','important','optional') NOT NULL,
    is_required_snapshot    TINYINT(1) NOT NULL DEFAULT 1,
    is_checked              TINYINT(1) NOT NULL DEFAULT 0,
    checked_at              DATETIME NULL,
    CONSTRAINT fk_sessitem_session FOREIGN KEY (session_id) REFERENCES checklist_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sessitem_belonging FOREIGN KEY (belonging_id) REFERENCES belongings(id) ON DELETE SET NULL,
    INDEX idx_sessitem_session (session_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- reminders
-- ---------------------------------------------------------------------
CREATE TABLE reminders (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNSIGNED NOT NULL,
    departure_schedule_id   INT UNSIGNED NOT NULL,
    reminder_type           ENUM('preparation','checklist','final_check','departure') NOT NULL,
    reminder_datetime       DATETIME NOT NULL,
    status                  ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_schedule FOREIGN KEY (departure_schedule_id) REFERENCES departure_schedules(id) ON DELETE CASCADE,
    UNIQUE KEY uq_reminder_dedupe (departure_schedule_id, reminder_type),
    INDEX idx_reminder_due (status, reminder_datetime)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- notifications (in-app notification center)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    reminder_id         INT UNSIGNED NULL,
    title               VARCHAR(150) NOT NULL,
    message             VARCHAR(500) NOT NULL,
    notification_type   ENUM('preparation','checklist','final_check','departure','system') NOT NULL DEFAULT 'system',
    read_at             DATETIME NULL,
    delivery_status     ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_reminder FOREIGN KEY (reminder_id) REFERENCES reminders(id) ON DELETE SET NULL,
    INDEX idx_notif_user_read (user_id, read_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- user_preferences
-- ---------------------------------------------------------------------
CREATE TABLE user_preferences (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                     INT UNSIGNED NOT NULL,
    default_reminder_intervals JSON NULL,     -- e.g. [30,15,5,0] minutes before departure
    notification_preferences   JSON NULL,     -- e.g. {"browser":true,"in_app":true}
    theme                       ENUM('dark','light') NOT NULL DEFAULT 'dark',
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_prefs_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- item_check_stats (rule-based personalization: per user/activity/item tally)
-- ---------------------------------------------------------------------
CREATE TABLE item_check_stats (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    activity_id         INT UNSIGNED NOT NULL,
    belonging_id        INT UNSIGNED NOT NULL,
    checked_count       INT UNSIGNED NOT NULL DEFAULT 0,
    unchecked_count     INT UNSIGNED NOT NULL DEFAULT 0,
    last_updated        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_stats_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_stats_belonging FOREIGN KEY (belonging_id) REFERENCES belongings(id) ON DELETE CASCADE,
    UNIQUE KEY uq_stats (user_id, activity_id, belonging_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- recommendation_actions (Phase 4: accept/dismiss on a "frequently
-- unchecked -> prioritize this?" suggestion, so it only surfaces once)
-- ---------------------------------------------------------------------
CREATE TABLE recommendation_actions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    activity_id     INT UNSIGNED NOT NULL,
    belonging_id    INT UNSIGNED NOT NULL,
    action          ENUM('accepted','dismissed') NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_recaction_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    CONSTRAINT fk_recaction_belonging FOREIGN KEY (belonging_id) REFERENCES belongings(id) ON DELETE CASCADE,
    UNIQUE KEY uq_recaction (user_id, activity_id, belonging_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SAMPLE / DEMO DATA  (clearly labeled — safe to delete before deployment)
-- Demo login: alex@checkngo.test / password: Password123!
-- Password hash below corresponds to "Password123!" (bcrypt)
-- =====================================================================

INSERT INTO users (full_name, email, password_hash, role) VALUES
('Alex Santos', 'alex@checkngo.test', '$2y$10$3sQe1z1qU1z1h8gk1qgS7uYQKcW2xkZP2nQeM0dQwF2Hh1qFq0iM6', 'student');
-- NOTE: Regenerate this hash locally with password_hash('Password123!', PASSWORD_DEFAULT)
-- before relying on it — hashes are salted per-generation and this literal string
-- is illustrative only. See README for the exact command to run.

INSERT INTO activities (user_id, activity_name, activity_type, destination_label, icon) VALUES
(1, 'School', 'school', 'University Campus', 'fa-graduation-cap'),
(1, 'Work', 'work', 'Main Office', 'fa-briefcase'),
(1, 'Travel', 'travel', 'Airport Terminal 2', 'fa-plane'),
(1, 'Personal', 'personal', 'Errands', 'fa-user'),
(1, 'Examination', 'examination', 'Room 301', 'fa-file-pen'),
(1, 'Laboratory', 'laboratory', 'IT Laboratory', 'fa-flask');

INSERT INTO belongings (user_id, item_name, category, default_priority) VALUES
(1, 'Wallet', 'Personal', 'important'),
(1, 'Keys', 'Personal', 'important'),
(1, 'ID Card', 'Documents', 'critical'),
(1, 'Charger', 'Electronics', 'important'),
(1, 'Notebook', 'School', 'important'),
(1, 'Laptop', 'Electronics', 'critical'),
(1, 'Phone', 'Electronics', 'critical'),
(1, 'Passport', 'Documents', 'critical'),
(1, 'USB Drive', 'Electronics', 'important'),
(1, 'Headphones', 'Electronics', 'optional'),
(1, 'Water Bottle', 'Personal', 'optional');

-- Sample checklist template for Laboratory (activity_id = 6)
INSERT INTO checklist_templates (user_id, activity_id, template_name, is_default) VALUES
(1, 6, 'IT Laboratory Default', 1);

INSERT INTO checklist_template_items (template_id, belonging_id, priority, is_required, sort_order) VALUES
(1, 6, 'critical', 1, 1),   -- Laptop
(1, 3, 'critical', 1, 2),   -- ID Card
(1, 9, 'important', 1, 3),  -- USB Drive
(1, 4, 'important', 1, 4);  -- Charger
