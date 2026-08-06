# Product description UTF-8 / mojibake repair

## Root cause

`HtmlSanitizer` used `DOMDocument::loadHTML()` **without a UTF-8 charset hint**. Libxml treated each UTF-8 byte as Latin-1 and re-emitted UTF-8, corrupting symbols such as `• – … “ ” ’ ° ™ ✅` on every admin create/update of:

- `products.description`
- `products.short_description`

Re-editing a corrupted product ran sanitize again and produced deeper mojibake (`Ã¢â‚¬…`).

Transport, MySQL `utf8mb4`, and storefront rendering were not the encoder.

## Deploy-before-repair rule

1. **Deploy the HtmlSanitizer UTF-8 fix first.**
2. Only then run the repair command write mode.
3. Do **not** re-save products in admin until the fix is live (that deepens corruption).

## Dry-run (safe, default)

```bash
docker compose exec api php artisan products:repair-description-encoding

# Optional single product:
docker compose exec api php artisan products:repair-description-encoding --product=<UUID>
```

Reports product ID/name, field, corruption depth, and before/after preview. Skips ambiguous rows. Makes no writes.

## Write mode (guarded)

```bash
docker compose exec api php artisan products:repair-description-encoding \
  --force \
  --confirm=REPAIR_PRODUCT_DESCRIPTION_ENCODING

# Optional scope:
docker compose exec api php artisan products:repair-description-encoding \
  --product=<UUID> \
  --force \
  --confirm=REPAIR_PRODUCT_DESCRIPTION_ENCODING
```

Safeguards:

- Default is dry-run
- Write requires `--force` **and** exact `--confirm=REPAIR_PRODUCT_DESCRIPTION_ENCODING`
- Only updates `description` / `short_description` when markers decrease and decode is valid UTF-8
- Skips ambiguous/unrecoverable fields
- Does **not** touch `meta_description`
- No deletes
- Transactional per-product update
- Logs `product_description_encoding_repaired` with product id/name/slug and field summary

## Verification

```bash
# Sanitizer must preserve bullet (hex e280a2)
docker compose exec api php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo bin2hex(App\Support\Security\HtmlSanitizer::sanitize("•")), PHP_EOL;'

# Spot remaining mojibake after repair
docker compose exec api php artisan tinker --execute="echo App\Models\Product::query()->where('description','like','%â%')->orWhere('description','like','%Ã%')->orWhere('short_description','like','%â%')->orWhere('short_description','like','%Ã%')->count();"

# SQL
# SELECT id, name, LEFT(description,80) FROM products
# WHERE description LIKE '%â%' OR description LIKE '%Ã%'
#    OR short_description LIKE '%â%' OR short_description LIKE '%Ã%';
```

## Related code

- `apps/api/app/Support/Security/HtmlSanitizer.php`
- `apps/api/app/Support/Catalog/ProductDescriptionEncodingRepair.php`
- `apps/api/app/Console/Commands/RepairProductDescriptionEncodingCommand.php`
