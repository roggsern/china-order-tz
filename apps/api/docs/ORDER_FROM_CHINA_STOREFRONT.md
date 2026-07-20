# ORDER FROM CHINA Storefront (TASK 044B)

China import navigation is separate from BUY FROM TZ.

## Label

Use **ORDER FROM CHINA** (not “Buy From China”).

## Data scope

| Include | Exclude |
|---------|---------|
| Catalog Bible china categories | Store-scoped categories |
| Brands with CHINA_IMPORT products | TZ_LOCAL products |
| Published, active, non-demo China products | Inactive / draft / unpublished |
| | Faker sample category roots |
| | Department flat starter roots (inactive for nav) |

## APIs

- `GET /storefront/china/menu?category=`
- `GET /storefront/china/categories`
- `GET /storefront/china/brands?category=`
- `GET /storefront/china/products?category=&brand=&featured=`

## Web

Header mega menu: `MegaMenu` → ORDER FROM CHINA  
Viewport-limited dropdown with categories → brands → featured products.
