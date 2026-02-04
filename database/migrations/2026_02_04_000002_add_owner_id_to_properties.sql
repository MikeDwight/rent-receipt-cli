-- Rebuild properties table to add owner_id with a proper FK.
-- Do NOT rely on PRAGMA foreign_keys toggling (migrations run inside a transaction).

CREATE TABLE properties_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    address TEXT NOT NULL,
    rent_amount INTEGER NOT NULL,
    charges_amount INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES owners(id)
);

-- Backfill: assign all existing properties to the first owner found.
-- If owners table is empty, this will insert 0 rows and the migration will fail later (which is correct).
INSERT INTO properties_new (id, owner_id, label, address, rent_amount, charges_amount, created_at)
SELECT
    p.id,
    (SELECT o.id FROM owners o ORDER BY o.id ASC LIMIT 1) AS owner_id,
    p.label,
    p.address,
    p.rent_amount,
    p.charges_amount,
    p.created_at
FROM properties p;

DROP TABLE properties;
ALTER TABLE properties_new RENAME TO properties;
