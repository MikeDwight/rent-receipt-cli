CREATE TABLE owners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    address TEXT NOT NULL,
    created_at TEXT NOT NULL
);

-- Default owner placeholder (to be updated via admin CLI)
INSERT INTO owners (full_name, email, address, created_at)
VALUES ('Bailleur', '', '', datetime('now'));
