# Rent Receipt CLI

CLI tool (PHP 8) to generate rent receipt PDFs, send them by email (SMTP), and archive them to Nextcloud (WebDAV).

---

## Features (V1)

- Generate rent receipts as PDFs
- Track receipts and payments in SQLite
- Send receipts by email via SMTP
- Archive PDFs to Nextcloud via WebDAV
- Local PDF fallback storage
- CLI-only (no web interface)

---

## Requirements

- PHP 8.x (CLI)
- Composer
- SQLite3
- wkhtmltopdf

---

## Installation

This is a CLI project. After cloning the repository, install dependencies with Composer.

---

## Database setup

The project uses a single SQLite database file (`database.sqlite`).

The schema is defined in `schema.sql`.

---

## Configuration

All sensitive configuration is done via environment variables.

A sample configuration file is provided as `.env.example`.

You must copy it to `.env` and fill in your own credentials.

---

## SMTP (Email)

Emails are sent via SMTP (tested with Gmail App Passwords).

The `sent_at` field means that the SMTP server accepted the message.
Final delivery may still fail (bounces are out of scope for V1).

---

## Nextcloud (WebDAV)

Generated PDFs can be archived to Nextcloud via WebDAV.

The target directory is configurable using:

NEXTCLOUD_TARGET_DIR

Example:

/Perso/Investissements/JJ Rousseau/test-quittance

---

## Usage

Generate receipts for a month:

php bin/rent-receipt receipt:generate 2026-06

Send and archive receipts:

php bin/rent-receipt receipt:send 2026-06

Dry-run (no email, no upload):

php bin/rent-receipt receipt:send 2026-06 --dry-run

Check status:

php bin/rent-receipt receipt:send:status 2026-06

---

## Limitations (V1)

- SMTP acceptance does not guarantee final delivery
- No bounce email processing
- No web interface
- Single landlord, single database

---

## License

MIT
