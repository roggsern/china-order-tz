# BLAZER soft-delete recovery

Product `019fd2c4-e831-7115-a769-88860ecb6f94` (BLAZER) was soft-deleted at
`2026-08-05 17:10:33 UTC` via the admin delete endpoint. Variants were left
active (pre-cascade behavior). Order item snapshots must not be deleted.

## Option A — accidental delete: restore product

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan tinker --execute="
    \$p = \\App\\Models\\Product::onlyTrashed()->find('019fd2c4-e831-7115-a769-88860ecb6f94');
    app(\\App\\Services\\AdminProducts\\ProductDeletionLifecycle::class)->restore(\$p);
    echo 'restored='.(\$p->fresh()->trashed() ? 'no' : 'yes');
  "
```

Or use Admin UI Trash → Restore (after deploy of cascade restore).

For **legacy orphans** soft-deleted after the product (via reconcile), restore
will bring back variants whose `deleted_at >= product.deleted_at - 2s`.

## Option B — keep deleted: soft-delete orphan variants

```bash
# Inspect
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan catalog:reconcile-trashed-product-variants \
  --product=019fd2c4-e831-7115-a769-88860ecb6f94 \
  --dry-run

# Apply
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan catalog:reconcile-trashed-product-variants \
  --product=019fd2c4-e831-7115-a769-88860ecb6f94 \
  --force
```

## Verify

```bash
# Active admin list must 200
# Trash list must 200 and may report catalog_integrity.orphaned_active_variants_count
# Order snapshots for BLAZER line items remain readable
```

Do **not** force-delete BLAZER if order history references it unless you accept
hard-removal of catalog rows (order snapshots still keep names/prices).
