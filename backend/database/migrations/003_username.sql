ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(40) NULL AFTER id;

UPDATE users SET username = LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(email, '@', 1), '[^a-zA-Z0-9._-]', '')) WHERE username IS NULL AND CHAR_LENGTH(REGEXP_REPLACE(SUBSTRING_INDEX(email, '@', 1), '[^a-zA-Z0-9._-]', '')) >= 3 AND id = (SELECT MIN(id) FROM (SELECT id, email FROM users) u2 WHERE LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(u2.email, '@', 1), '[^a-zA-Z0-9._-]', '')) = LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(users.email, '@', 1), '[^a-zA-Z0-9._-]', '')));

UPDATE users SET username = CONCAT('user', id) WHERE username IS NULL;

ALTER TABLE users MODIFY username VARCHAR(40) NOT NULL;

ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS uq_users_username (username)
