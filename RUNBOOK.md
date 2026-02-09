# Rent Receipt CLI — Runbook (Operations)

This document describes the **operational procedures** for using the Rent Receipt CLI safely and reliably.
It is intended as a day-to-day reference and an operational checklist.

---

## 0) Environment preflight (mandatory)

Load environment variables:

```bash
set -a
source env.local.sh
set +a
```

Verify configuration (no secrets exposed):

```bash
php bin/rent-receipt receipt:env:check
```

Do **not** proceed if this check fails.

---

## 1) Database lifecycle

### Reset database (clean state)

```bash
rm -f database.sqlite
php bin/rent-receipt db:migrate
php bin/rent-receipt db:status
```

SQLite is the single source of truth and can always be recreated from migrations.

---

## 2) Bootstrap reference data

```bash
php bin/rent-receipt seed:import
```

Seed behavior (intentional):
- Creates owner
- Creates property
- Does NOT create tenants or payments

Verification:

```bash
php bin/rent-receipt owner:list
php bin/rent-receipt property:list
php bin/rent-receipt tenant:list
```

---

## 3) Tenant management

Create or update a tenant:

```bash
php bin/rent-receipt tenant:upsert \
  --full-name="Jean Dupont" \
  --email="jean.dupont@example.com" \
  --address="12 rue de Test, 75000 Paris"
```

Verify:

```bash
php bin/rent-receipt tenant:list
php bin/rent-receipt tenant:show 1
```

---

## 4) Payment management (business trigger)

Create a rent payment:

```bash
php bin/rent-receipt payment:upsert \
  --tenant-id=1 \
  --property-id=1 \
  --period=2026-02 \
  --rent=49000 \
  --charges=5000 \
  --paid-at=2026-02-09
```

Verify:

```bash
php bin/rent-receipt payment:list
php bin/rent-receipt payment:show 1
```

A payment for a given month is the prerequisite for receipt generation.

---

## 5) Receipt generation (PDF)

```bash
php bin/rent-receipt receipt:generate 2026-02
ls -lah var/receipts
```

Rules:
- One receipt per payment and month
- Generation is idempotent (existing receipts are skipped)

---

## 6) Send & archive — dry-run

```bash
php bin/rent-receipt receipt:send 2026-02 --dry-run
php bin/rent-receipt receipt:send:status 2026-02
```

Dry-run performs no email sending and no upload.

---

## 7) Send & archive — real execution

```bash
php bin/rent-receipt receipt:send 2026-02
php bin/rent-receipt receipt:send:status 2026-02
```

Database verification:

```bash
sqlite3 database.sqlite \
"SELECT id, sent_at, archived_at FROM receipts ORDER BY id DESC LIMIT 5;"
```

Meaning:
- sent_at → email accepted by SMTP
- archived_at → PDF archived to Nextcloud

---

## 8) Retry archive-only (recovery)

If a receipt was sent but not archived:

```bash
sqlite3 database.sqlite "UPDATE receipts SET archived_at = NULL WHERE id = 1;"
php bin/rent-receipt receipt:send 2026-02
```

Behavior:
- No email resent
- Archive retried

---

## 9) Logs & diagnostics

```bash
ls -lah var/log
tail -n 200 var/log/error.log
tail -n 200 var/log/app.log
```

Use logs to diagnose SMTP, PDF generation, WebDAV, or database issues.

---

## Monthly checklist (quick)

```bash
source env.local.sh
php bin/rent-receipt receipt:env:check
php bin/rent-receipt payment:upsert (...)
php bin/rent-receipt receipt:generate YYYY-MM
php bin/rent-receipt receipt:send YYYY-MM --dry-run
php bin/rent-receipt receipt:send YYYY-MM
php bin/rent-receipt receipt:send:status YYYY-MM
```

---

End of runbook.
