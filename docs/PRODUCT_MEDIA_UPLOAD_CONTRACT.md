# Product media upload contract

Canonical admin catalog product/variant image upload policy.

## Supported formats

- JPG / JPEG
- PNG
- WebP

**Not supported:** HEIC / HEIF (reject with guidance to export/save as JPG, PNG, or WebP). No server-side HEIC conversion.

## Limits

| Layer | Value |
|-------|--------|
| Client validation | 10 MB |
| Laravel `product_media` validation | `max:10240` (10 MB), max dimensions 5000×5000 |
| PHP `upload_max_filesize` | **10M** (`docker/php/uploads.prod.ini`) |
| PHP `post_max_size` | **12M** |
| PHP `memory_limit` | **256M** |
| Nginx `client_max_body_size` | **20M** (`docker/nginx/default.conf`) |

Shared PHP/Laravel constant source: `App\Support\ProductMedia\ProductMediaUploadContract`.  
Shared frontend helper: `apps/web/src/lib/admin/product-media-upload.ts` (used by product media, variant media, and attribute-option apply).

## Live admin entry points

Shared client helper: `apps/web/src/lib/admin/product-media-upload.ts`

Used by:
- `ProductMediaManager`
- `VariantMediaManager`
- `VariantAttributeImageApply`

Client accepts MIME aliases (`image/png`, `image/x-png`, `image/jpeg`, `image/jpg`, `image/pjpeg`, `image/webp`), allowed extensions, and (for empty/`application/octet-stream`) PNG/JPEG/WebP magic-byte sniffing. Format errors never mention the 10 MB limit.

## Optional diagnostics

```bash
PRODUCT_MEDIA_UPLOAD_DIAGNOSTICS=true
```

When enabled, failed/attempted catalog uploads log only: original name, client MIME, sniffed MIME, guessed extension, size, upload error, isValid. Default **false**.

## Legacy endpoint (not live UI)

`POST /api/v1/admin/products/{id}/images` (`StoreProductImageRequest`) remains at **2 MB**.  
It is not used by the live product/variant media managers. Do not treat it as the production catalog media contract.

## VPS verification (after API image rebuild)

```bash
docker compose -f docker-compose.prod.yml exec -T api php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"
docker compose -f docker-compose.prod.yml exec -T api php artisan ops:upload-limits
docker compose -f docker-compose.prod.yml exec -T nginx sh -c "grep client_max_body_size /etc/nginx/conf.d/default.conf"
```

Expected PHP:

```
upload_max_filesize => 10M
post_max_size => 12M
memory_limit => 256M
```

Expected Nginx: `client_max_body_size 20M` (must remain ≥ 10M).

## Deploy notes

1. Rebuild/restart **api**, **queue**, and **scheduler** so they pick up `uploads.prod.ini` from the PHP image.
2. No DB migration.
3. Web can ship independently (client copy + error mapping).
