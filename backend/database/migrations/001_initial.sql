CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('active','paused','completed','archived') NOT NULL DEFAULT 'active',
  start_date DATE NULL,
  due_date DATE NULL,
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_parent FOREIGN KEY (parent_id) REFERENCES projects (id) ON DELETE CASCADE,
  INDEX idx_projects_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#64748b',
  position INT NOT NULL DEFAULT 0,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_phases_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  INDEX idx_phases_project (project_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  phase_id INT UNSIGNED NOT NULL,
  title VARCHAR(250) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  due_date DATE NULL,
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_phase FOREIGN KEY (phase_id) REFERENCES phases (id) ON DELETE CASCADE,
  INDEX idx_tasks_phase (phase_id, position),
  INDEX idx_tasks_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
