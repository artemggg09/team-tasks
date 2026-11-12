-- Схема для SQLite
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'in_progress', 'done')),
  priority VARCHAR(10) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
  due_date DATE NULL,
  assignee_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_assignee ON tasks(assignee_id);
CREATE INDEX IF NOT EXISTS idx_tasks_due ON tasks(due_date);

-- Неудачные попытки входа (защита от перебора)
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  throttle_key VARCHAR(64) NOT NULL,
  attempted_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_key ON login_attempts(throttle_key, attempted_at);
