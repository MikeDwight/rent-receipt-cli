-- Baseline schema for a fresh database

CREATE TABLE IF NOT EXISTS schema_migrations (
    version TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS owners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    address TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    address TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    address TEXT NOT NULL,
    rent_amount INTEGER NOT NULL,
    charges_amount INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES owners(id)
);

CREATE TABLE IF NOT EXISTS rent_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    property_id INTEGER NOT NULL,
    period TEXT NOT NULL,
    rent_amount INTEGER NOT NULL,
    charges_amount INTEGER NOT NULL,
    paid_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE (tenant_id, property_id, period),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (property_id) REFERENCES properties(id)
);

CREATE TABLE IF NOT EXISTS receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rent_payment_id INTEGER NOT NULL,
    pdf_path TEXT NOT NULL,
    sent_at TEXT,
    archived_at TEXT,
    created_at TEXT NOT NULL,
    send_error TEXT,
    archive_path TEXT,
    archive_error TEXT,
    UNIQUE (rent_payment_id),
    FOREIGN KEY (rent_payment_id) REFERENCES rent_payments(id)
);

-- Default owner (placeholder)
INSERT INTO owners (full_name, email, address, created_at)
VALUES ('Bailleur', '', '', datetime('now'));
