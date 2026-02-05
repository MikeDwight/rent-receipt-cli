-- Adds send & archive tracking fields to receipts
-- NOTE: SQLite does not support "ADD COLUMN IF NOT EXISTS".
-- This migration must be applied only once on a fresh database.

ALTER TABLE receipts ADD COLUMN sent_at TEXT NULL;
ALTER TABLE receipts ADD COLUMN send_error TEXT NULL;

ALTER TABLE receipts ADD COLUMN archived_at TEXT NULL;
ALTER TABLE receipts ADD COLUMN archive_path TEXT NULL;
ALTER TABLE receipts ADD COLUMN archive_error TEXT NULL;
