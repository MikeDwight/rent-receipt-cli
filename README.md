# Rent Receipt CLI

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

### One-click payment workflow (recommended)

The `receipt:process` command is the **standard workflow** for daily operations: when a payment is received, one command handles the entire flow automatically.

```bash
php bin/rent-receipt receipt:process --tenant-id=1 --property-id=1 --yes
```

**What it does automatically:**
- Detects current month (Europe/Paris timezone)
- Upserts the payment (idempotent: updates if exists, creates if new)
- Generates PDF receipt if not already generated
- Sends email if not already sent
- Archives to Nextcloud if not already archived

**Key features:**
- **Idempotent**: safe to re-run multiple times (skips already-completed steps)
- **Dry-run**: `--dry-run` for simulation without any side effects
- **Recovery flags**: `--rearchive` to retry archive-only, `--resend` to force email resend (use sparingly)
- **Mobile-friendly**: can be triggered from phone via SSH/Tailscale or automation apps

See the Runbook section **"Mobile / One-click payment flow (recommended)"** for detailed operational procedures.

---

## Architecture & Design

The application follows a layered architecture with strict separation of concerns:

### Core domain
Contains business rules related to receipt generation and payment logic.  
No infrastructure or framework dependencies.

### Application layer
Orchestrates use cases (generate receipts, send receipts).  
Coordinates domain logic with external services via ports.

### Infrastructure layer
Implements technical details such as:
- SQLite repositories
- SMTP email sending
- WebDAV (Nextcloud) archiving

### CLI
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
- wkhtmltopdf binary available on `PATH` (or configured via env)

---

## Installation

```bash
composer install
cp .env.example .env
```

Configure your `.env` (SMTP, Nextcloud, storage paths, etc.).

Create / migrate the SQLite database:

```bash
php bin/rent-receipt db:migrate
```

Optionally import initial reference data:

```bash
php bin/rent-receipt seed:import
```

---

## 📘 Runbook — Operations (A to Z)

See full operational guide in the RUNBOOK.
