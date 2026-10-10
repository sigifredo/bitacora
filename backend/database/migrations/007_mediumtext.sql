-- TEXT admite 65 535 bytes, no caracteres: con UTF-8 multibyte (hasta 4 bytes por carácter) una descripción
-- de 65 000 caracteres o un checklist completo (100 elementos de 250) lo superan. MEDIUMTEXT admite 16 MB.
ALTER TABLE projects MODIFY description MEDIUMTEXT NULL;

ALTER TABLE tasks MODIFY description MEDIUMTEXT NULL;

ALTER TABLE tasks MODIFY checklist MEDIUMTEXT NULL;

INSERT IGNORE INTO schema_migrations (name) VALUES ('007_mediumtext.sql')
