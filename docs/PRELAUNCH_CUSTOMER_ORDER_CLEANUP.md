# Pre-launch customer / order cleanup (catalog preserved)

Use this instead of `production:cleanup-commerce-data` when products must remain.

## Command

```bash
php artisan production:cleanup-customer-order-data --dry-run

php artisan production:cleanup-customer-order-data \
  --force \
  --confirm=DELETE_PRELAUNCH_CUSTOMERS_AND_ORDERS
```

## VPS runbook

1. **Backup first** (required — order evidence only survives in the backup):

```bash
# example — adapt to your backup tooling
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T db \
  mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
  > "backup-prelaunch-$(date -u +%Y%m%dT%H%M%SZ).sql"
```

2. **Dry-run** (prints env/host/db, customers, orders, provenance, counts):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-customer-order-data --dry-run
```

Verify:
- Environment = `production`
- Correct database host/name
- Expected customer emails (including `sepprisegetsfashion@gmail.com`)
- Expected order numbers (including `COTZ-20260805-000002`)
- Product / variant / inventory / media counts that will **remain**
- Provenance `proven_path` for Sepprise (do not invent a path if `cannot_be_proven`)

3. **Execute**:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-customer-order-data \
  --force \
  --confirm=DELETE_PRELAUNCH_CUSTOMERS_AND_ORDERS
```

4. **Confirm idempotency**:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-customer-order-data --dry-run
```

Expect: `Customer users to delete: 0`, no orders listed, catalog preserve counts unchanged.

## Preserves

Admins/roles, products (including soft-deleted), variants, variant prices, inventory /
commercial stock, product media, taxonomy, suppliers, CMS, settings, shipping/payment
config, warehouse facilities, notification templates.

## Deletes

Customers, CRM runtime, tokens/sessions, carts/checkouts/wishlists, orders and
dependency graph (payments, refunds, fulfillments, shipments, tracking, notifications,
loyalty ledger, support tickets, order-driven warehouse/procurement runtime, analytics).

## Does not

- Delete or modify products / variants / media / stock balances
- Truncate, wipe, or disable foreign keys
- Replace a missing DB backup
