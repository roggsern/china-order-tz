# Production commerce data cleanup (RC1)

Safe, explicit wipe of **test/demo commerce data** before real catalog + live NMB payments.

**Does not** reset the platform foundation (admins, stores, taxonomy, CMS, settings, templates).

**Do not** confuse with local-only `app:reset-commerce-data` / `app:reset-catalog-data`.

---

## Command

```bash
# Preview only (default without --force also makes no writes)
php artisan production:cleanup-commerce-data --dry-run

# Destructive (requires both flags)
php artisan production:cleanup-commerce-data \
  --force \
  --confirm=DELETE_TEST_COMMERCE_DATA
```

Compose (production host):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-commerce-data --dry-run

docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-commerce-data \
  --force --confirm=DELETE_TEST_COMMERCE_DATA
```

---

## What is deleted vs preserved

### Deleted (transactional / demo commerce)

Products & variants, product media rows + `products/` disk files, inventory movements/stock rows, China commercial stock & procurement board rows, carts/wishlists/checkout, customer `users` (+ CRM profiles/addresses/tokens/sessions), orders/payments/refunds/returns, fulfillments/shipments/tracking/warehouse ops docs, China customer orders (+ attachment files under known prefixes), support tickets, notification **instances**, storefront/AI analytics, loyalty **accounts/ledger**, coupon/promotion **usages**, growth **memberships/deliveries**.

### Preserved

`admins`, roles/permissions, stores & store admin assignments, departments/categories, catalog product types & attributes/options/mappings, suppliers master, warehouse facilities/zones/bins, shipping/payment method config, settings, notification **templates**, CMS layouts/nav/campaigns, polymorphic CMS `media`, loyalty/growth/coupon **definitions**, migrations, jobs queues (not blindly truncated).

Customer vs admin: separate tables (`users` vs `admins`). Command aborts if that separation cannot be proven.

---

## Exact VPS procedure (execute later — not from this agent)

Use permanent production Compose only (`docker-compose.yml` + `docker-compose.prod.yml`).

1. **Fresh verified backup (DB + media)**

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api php artisan ops:backup-check
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api php artisan ops:backup-run
# Also snapshot media volume / public disk if backup does not already cover it
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api php artisan ops:backup-media
```

2. **Temporary commerce freeze** (if available in ops runbook — disable storefront checkout / put maintenance notice). If no freeze feature exists, schedule a short maintenance window and stop public traffic at the reverse proxy.

3. **Dry run**

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-commerce-data --dry-run
```

4. **Review counts** — confirm environment, DB host/name, customer delete count, preserve checks nonzero where expected, media file plan.

5. **Destructive execution**

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan production:cleanup-commerce-data \
  --force --confirm=DELETE_TEST_COMMERCE_DATA
```

6. **Post-cleanup counts** — re-run `--dry-run`; commerce domains should be zero; preserve checks intact.

7. **Health**

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api php artisan ops:health
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T queue php artisan ops:queue-health
```

8. **Smoke test** — admin login, storefront home/CMS, empty catalog, create one real product draft.

9. **Fresh post-cleanup backup** — `ops:backup-run` (+ media).

10. **Begin real product creation** and live NMB payment testing.

---

## Safety controls

- No writes without `--force` (and `--dry-run` alone never writes).
- Destructive requires exact `--confirm=DELETE_TEST_COMMERCE_DATA`.
- Prints environment, DB host, DB name (never passwords).
- FK-safe ordered deletes inside a DB transaction; failure rolls back.
- No `DROP TABLE`, `TRUNCATE`, `migrate:fresh`, or `db:wipe`.
- Media: only paths collected from product/china-order rows under safe prefixes; CMS/store branding untouched.
- Idempotent: safe to re-run when already clean.

---

## Rollback

Restore from the pre-cleanup backup taken in step 1. Do not re-import the compromised pre-incident MySQL volume.
