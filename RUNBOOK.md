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

## 4) Mobile / One-click payment flow (recommended)

**This is the standard workflow for daily operations.** When a payment is received, use `receipt:process` to handle the entire flow in one command.

### Prerequisites

Same as the rest of the runbook:
- Load environment: `set -a && source env.local.sh && set +a`
- Verify config: `php bin/rent-receipt receipt:env:check`
- Ensure tenant and property exist (see sections 2-3)

### Dry-run (validation)

Always validate with dry-run first:

```bash
php bin/rent-receipt receipt:process --tenant-id=1 --property-id=1 --dry-run
```

Output shows what would happen without any side effects (no DB write, no PDF, no email, no upload).

### Real execution

```bash
php bin/rent-receipt receipt:process --tenant-id=1 --property-id=1 --yes
```

**What happens automatically:**
- Payment upserted (uses property defaults for rent/charges, updates if payment already exists)
- PDF generated if not already generated
- Email sent if not already sent
- PDF archived to Nextcloud if not already archived

**Idempotence:** Safe to re-run. Already-completed steps are skipped automatically.

### Optional overrides

- `--period=YYYY-MM` — override default (current month Europe/Paris)
- `--paid-at=YYYY-MM-DD` — override default (today Europe/Paris)

### Retry archive-only

If email was sent but archive failed:

```bash
php bin/rent-receipt receipt:process --tenant-id=1 --property-id=1 --rearchive --yes
```

This retries archive without resending email.

### Resend email (exceptionnel)

Only if tenant did not receive the email:

```bash
php bin/rent-receipt receipt:process --tenant-id=1 --property-id=1 --resend --yes
```

Use sparingly.

---

## 5) Payment management (manual / batch)

**Note:** For daily operations, use `receipt:process` (section 4) which handles payment upsert automatically.  
The commands below are for manual payment management or batch operations.

Create a rent payment manually:

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

---

## 6) Receipt generation (PDF) — batch workflow

```bash
php bin/rent-receipt receipt:generate 2026-02
ls -lah var/receipts
```

Rules:
- One receipt per payment and month
- Generation is idempotent (existing receipts are skipped)

---

## 7) Send & archive — batch workflow

**Note:** For daily operations, use `receipt:process` (section 4).  
The commands below (`receipt:generate` and `receipt:send`) are for **batch monthly processing** or **audit/catch-up** scenarios.

### Dry-run

```bash
php bin/rent-receipt receipt:send 2026-02 --dry-run
php bin/rent-receipt receipt:send:status 2026-02
```

Dry-run performs no email sending and no upload.

### Real execution

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

## 8) Retry archive-only (batch recovery)

If a receipt was sent but not archived (batch workflow):

```bash
sqlite3 database.sqlite "UPDATE receipts SET archived_at = NULL WHERE id = 1;"
php bin/rent-receipt receipt:send 2026-02
```

Behavior:
- No email resent
- Archive retried

**Alternative:** Use `receipt:process --rearchive` (section 4) for single-payment retry.

---

## 9) Logs & diagnostics

```bash
ls -lah var/log
tail -n 200 var/log/error.log
tail -n 200 var/log/app.log
```

Use logs to diagnose SMTP, PDF generation, WebDAV, or database issues.

---

## 10) Monthly checklist

### Daily workflow (recommended)

When a payment is received:

```bash
source env.local.sh
php bin/rent-receipt receipt:env:check
php bin/rent-receipt receipt:process --tenant-id=X --property-id=Y --yes
```

### Batch monthly workflow (alternative)

For processing multiple payments at once or monthly catch-up:

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
