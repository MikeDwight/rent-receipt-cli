PRAGMA foreign_keys = ON;

CREATE TABLE tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    address TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    address TEXT NOT NULL,
    rent_amount INTEGER NOT NULL,
    charges_amount INTEGER NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE rent_payments (
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

CREATE TABLE receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rent_payment_id INTEGER NOT NULL,
    pdf_path TEXT NOT NULL,
    sent_at TEXT,
    archived_at TEXT,
    created_at TEXT NOT NULL,
    UNIQUE (rent_payment_id),
    FOREIGN KEY (rent_payment_id) REFERENCES rent_payments(id)
);
