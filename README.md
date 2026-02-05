## Rent Receipt CLI

Rent Receipt CLI is a PHP 8 command-line tool designed to generate, send, and archive rent receipts in a reliable and traceable way.

This project was built as a real-world utility for a small landlord use case, with a strong focus on:

- correctness and traceability
- clean architecture
- long-term maintainability

---

## Why this project exists

Managing rent receipts manually is error-prone and time-consuming.  
This tool addresses a simple but recurring operational need: generating legally consistent rent receipts, sending them to tenants, and keeping a reliable archive.

The goal is **not** to build a SaaS platform, but a robust, auditable CLI tool that can be run monthly with confidence.

---

## Why a CLI tool?

This project intentionally uses a CLI-first approach:

- The workflow is batch-oriented (monthly receipts)
- No need for a permanent web interface
- Fewer moving parts, simpler deployment
- Easier to test and reason about

The CLI is strictly an interface layer and never contains business logic.

---

## Features (V1)

- Generate rent receipts as PDFs
- Track receipts and payments in SQLite
- Send receipts by email via SMTP
- Archive PDFs to Nextcloud via WebDAV
- Local PDF fallback storage
- CLI-only (no web interface)
- Admin CLI to manage owners, tenants, properties, payments and receipts

---

## Architecture & Design

The application follows a layered architecture with strict separation of concerns:

- **Core domain**  
  Contains business rules related to receipt generation and payment logic.  
  No infrastructure or framework dependencies.

- **Application layer**  
  Orchestrates use cases (generate receipts, send receipts).  
  Coordinates domain logic with external services via ports.

- **Infrastructure layer**  
  Implements technical details such as:
  - SQLite repositories
  - SMTP email sending
  - WebDAV (Nextcloud) archiving

- **CLI**  
  Acts as a thin delivery mechanism.  
  Parses user input and delegates execution to application use cases.

---

## Key Design Decisions

- **PHP 8 (CLI)**  
  Chosen for reliability, strong typing, and ease of deployment.

- **SQLite**  
  Single-file database used as the single source of truth.  
  Well-suited for a personal or small-scale professional tool.

- **wkhtmltopdf**  
  Used for reliable HTML-to-PDF rendering with predictable output.

- **SMTP + WebDAV**  
  Simple, well-understood protocols with minimal vendor lock-in.

---

## Requirements

- PHP 8.x (CLI)
- Composer
- SQLite3
- wkhtmltopdf binary available on `PATH` (or configured)

---

## Installation

```bash
composer install
cp .env.example .env

# Configure your .env (SMTP, Nextcloud, storage paths, etc.)

# Create / migrate the SQLite database
php bin/rent-receipt db:migrate

# Optionally import initial data from seed/seed.yml
php bin/rent-receipt seed:import

# First dry-run for a given month
php bin/rent-receipt receipt:generate 2026-06
php bin/rent-receipt receipt:send 2026-06 --dry-run
```

---

## Database

The project uses a single SQLite database file (typically `database.sqlite` in the project data directory).

- SQLite is the single source of truth
- Receipts are generated once per payment and month
- Sending and archiving are tracked separately
- Local PDF storage acts as a fallback

The database schema is managed via SQLite migrations located in `database/migrations/`  
(for example `2026_02_01_000000_init_schema.sql`).  
This allows you to recreate a fresh database at any time from the migrations.

Additionally, a base schema (`schema.sql`) and a YAML seed file (`seed/seed.yml`) are provided
to bootstrap a realistic dataset via the `seed:import` command.

---

## Configuration

Configuration is done via environment variables (`.env`).

| Variable                        | Description         |
| ------------------------------- | ------------------- |
| SMTP_HOST / SMTP_PORT           | SMTP server         |
| SMTP_USER / SMTP_PASS           | SMTP credentials    |
| SMTP_FROM                       | Sender email        |
| NEXTCLOUD_BASE_URL              | WebDAV endpoint     |
| NEXTCLOUD_USER / NEXTCLOUD_PASS | WebDAV credentials  |
| NEXTCLOUD_TARGET_DIR            | Target directory    |
| WKHTMLTOPDF_BIN                 | Path to wkhtmltopdf |

If SMTP or Nextcloud are not configured, you can still run the tool in **dry-run**
or using the local-only archiver/sender implementations.

---

## SMTP (Email)

Emails are sent via SMTP (tested with Gmail App Passwords).

The `sent_at` field indicates that the SMTP server accepted the message.  
Final delivery (bounces) is intentionally out of scope for V1.

---

## Nextcloud (WebDAV)

Generated PDFs can be archived to Nextcloud via WebDAV.

The target directory is configurable using an environment variable, for example:

```bash
NEXTCLOUD_TARGET_DIR="/Perso/Investissements/JJ Rousseau/test-quittance"
```

---

## Usage

Generate receipts for a month:

```bash
php bin/rent-receipt receipt:generate 2026-06
```

Send and archive receipts:

```bash
php bin/rent-receipt receipt:send 2026-06
```

Dry-run (no email, no upload):

```bash
php bin/rent-receipt receipt:send 2026-06 --dry-run
```

Check status:

```bash
php bin/rent-receipt receipt:send:status 2026-06
```

---

## Admin CLI (Data management)

The project includes a minimal but complete **admin CLI layer** to manage all data
without editing the SQLite database manually.

This layer is designed for:

- real-world usage
- safety (destructive operations are guarded)
- auditability
- interview defensibility

### Database

```bash
php bin/rent-receipt db:status
php bin/rent-receipt db:migrate
php bin/rent-receipt db:migrate:status

### Owner

php bin/rent-receipt owner:list
php bin/rent-receipt owner:show <id>
php bin/rent-receipt owner:upsert --id=1 --full-name="..." --email="..." --address="..."
php bin/rent-receipt owner:delete <id> [--force]

### Tenant
php bin/rent-receipt tenant:list
php bin/rent-receipt tenant:show <id>
php bin/rent-receipt tenant:upsert --full-name="..." --email="..." --address="..."
php bin/rent-receipt tenant:delete <id> [--force]

###Properties
php bin/rent-receipt property:list
php bin/rent-receipt property:show <id>
php bin/rent-receipt property:upsert --owner-id=1 --label="..." --address="..." --rent=... --charges=...
php bin/rent-receipt property:delete <id> [--force]

All destructive operations are protected:
deletion is refused if related data exists
confirmation is required unless --force is used

---

## Limitations (V1 – intentional)

- SMTP acceptance does not guarantee final delivery
- No bounce email processing
- No web interface
- Single landlord, single database

These limitations are deliberate to keep the project focused, reliable, and easy to reason about.

---

## What this project demonstrates

- Clean, layered architecture with clear boundaries
- Pragmatic technical decision-making
- Attention to traceability and operational reliability
- The discipline to scope a product intentionally

---

## License

MIT
```
