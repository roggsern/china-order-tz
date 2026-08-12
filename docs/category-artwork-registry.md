# Shop-by-category artwork registry

Owned presentation assets for mobile (and future web parity).  
**Does not mutate backend taxonomy.** Category names remain UI text.

## Location

`apps/mobile/assets/images/categories/`

## Spec

| Property | Value |
|----------|-------|
| Aspect | **1:1** |
| Recommended size | **1024×1024** (or higher) |
| Style | Realistic editorial/commercial composite |
| Forbidden | Cartoons, emoji tiles, letter tiles, baked-in text, random stock dumps |
| Composition | 3–5 representative objects; crop-safe center |
| Background | Controlled neutral / brand-compatible cream–stone |
| Fallback | `generic.png` — never initials/emoji |

## Required filenames

| Filename | Department concept |
|----------|--------------------|
| `womens-fashion.png` | dress + handbag + heels + scarf |
| `mens-fashion.png` | blazer + belt + watch + shoes |
| `electronics.png` | phone + earbuds + tablet + watch |
| `beauty.png` | perfume + lipstick + compact + brush |
| `furniture.png` | vase + throw + lamp + bowl |
| `building-materials.png` | wood samples + hardware + level + tiles |
| `home-kitchen.png` | cookware + board + knife + towel |
| `home-care.png` | cleaning spray + toilet cleaner + disinfectant wipes + sponge/cloth + brush + pest-care (**no cookware**) |
| `kids-baby.png` | plush + wooden toy + blanket + shoes |
| `generic.png` | premium retail still-life fallback |

## Slug mapping

Presentation registry maps storefront/CMS slugs (and soft DepartmentSeeder aliases) → artwork keys in:

`apps/mobile/src/features/home/utils/categoryPresentation.ts`

Important: **`home-care` → `home-care.png`** (cleaning). It must **not** resolve to kitchen cookware.

Priority at runtime:

1. Server/CMS category image URL when present  
2. Owned artwork for resolved slug/name  
3. `generic.png`
