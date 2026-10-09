-- Compatible con MySQL y MariaDB: MySQL no admite ADD COLUMN IF NOT EXISTS, así que se comprueba a mano.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'checklist') = 0, 'ALTER TABLE tasks ADD COLUMN checklist TEXT NULL AFTER description', 'SELECT 1');

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (name) VALUES ('004_task_checklist.sql')
