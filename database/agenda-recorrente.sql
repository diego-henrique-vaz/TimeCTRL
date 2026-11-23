CREATE TABLE IF NOT EXISTS recurring_windows (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  user_id INTEGER NOT NULL,
  title VARCHAR(120) NOT NULL,
  kind VARCHAR(16) NOT NULL,
  weekdays INTEGER NOT NULL,
  first_day VARCHAR(10) NOT NULL,
  start_time VARCHAR(5) NOT NULL,
  end_time VARCHAR(5) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE (user_id, id)
);
CREATE TABLE IF NOT EXISTS window_exceptions (
  user_id INTEGER NOT NULL,
  rule_id INTEGER NOT NULL,
  occurrence_day VARCHAR(10) NOT NULL,
  change_kind VARCHAR(10) NOT NULL,
  start_time VARCHAR(5) NOT NULL,
  end_time VARCHAR(5) NOT NULL,
  release_time INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, rule_id, occurrence_day),
  FOREIGN KEY (user_id, rule_id) REFERENCES recurring_windows(user_id, id) ON DELETE CASCADE
);
