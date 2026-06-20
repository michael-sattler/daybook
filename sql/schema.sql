-- Daybook schema for MySQL (GreenGeeks cPanel)
-- Import this via phpMyAdmin into the database you create for this app.

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS priorities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS statuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sort_order INT NOT NULL,
  project_id INT NOT NULL,
  category_id INT NULL,
  subsystem VARCHAR(255) NOT NULL DEFAULT '',
  item_text TEXT NOT NULL,
  url VARCHAR(500) NOT NULL DEFAULT '',
  priority_id INT NULL,
  order_in_priority INT NOT NULL DEFAULT 0,
  status_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE SET NULL,
  FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS docs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  label VARCHAR(255) NOT NULL DEFAULT '',
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data --

INSERT INTO statuses (name, sort_order) VALUES
  ('TODO', 1), ('UNDERWAY', 2), ('OK', 3), ('N/A', 4), ('BLOCKED', 5);

INSERT INTO projects (name, sort_order) VALUES ('General', 1);

INSERT INTO categories (project_id, name, sort_order) VALUES
  (1,'bug',1),(1,'style',2),(1,'business',3),(1,'feature',4),
  (1,'rebuild',5),(1,'content',6),(1,'test',7),(1,'migrate',8),(1,'other',9);

INSERT INTO priorities (project_id, name, sort_order) VALUES
  (1,'1-Critical',1),(1,'2-High',2),(1,'3-Medium',3),(1,'4-Low',4),(1,'5-Someday',5);
