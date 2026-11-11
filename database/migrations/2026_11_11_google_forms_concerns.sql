-- Google Forms -> AssetVault concerns integration.
-- Run once in phpMyAdmin against the capstone_system database.
ALTER TABLE concerns
  ADD COLUMN reporter_name VARCHAR(180) NULL AFTER description,
  ADD COLUMN source VARCHAR(30) NOT NULL DEFAULT 'internal' AFTER reporter_name,
  ADD COLUMN external_submission_id VARCHAR(191) NULL AFTER source,
  ADD UNIQUE KEY uq_concerns_external_submission (external_submission_id);

-- Set this value to a long random secret, then use the exact same value
-- in the Apps Script project property ASSETVAULT_WEBHOOK_SECRET.
INSERT IGNORE INTO app_settings (k, v)
VALUES ('google_form_webhook_secret', 'REPLACE_WITH_A_LONG_RANDOM_SECRET');
