-- =====================================================================
-- Migration: add recommendation_actions (Phase 4)
-- Run this ONLY if you already imported checkngo_db.sql from an earlier
-- phase. Fresh installs can just re-import the full checkngo_db.sql —
-- this table is already included there.
-- =====================================================================
USE checkngo_db;

CREATE TABLE IF NOT EXISTS recommendation_actions (
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
