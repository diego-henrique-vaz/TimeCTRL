CREATE TABLE IF NOT EXISTS activity_details (
  activity_id INTEGER PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  interest_id INTEGER,
  FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
);
