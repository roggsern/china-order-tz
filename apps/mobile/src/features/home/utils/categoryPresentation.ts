import type { ImageSourcePropType } from 'react-native';

/**
 * Owned Shop-by-Category artwork registry + image resolver.
 * Taxonomy/slugs remain API-owned — this file is presentation only.
 *
 * Precedence:
 * 1. Explicit CMS/backend/category imageUrl (if non-empty)
 * 2. Known slug / slug-prefix family artwork
 * 3. Conservative name-token family artwork
 * 4. Neutral generic still-life
 *
 * Mapping is identity-based (slug/family), never array index.
 *
 * Artwork files live in `apps/mobile/assets/images/categories/`
 * Aspect: 1:1 · Recommended master: 1024×1024+ · No text in artwork.
 */

export type CategoryArtworkKey =
  | 'womens-fashion'
  | 'mens-fashion'
  | 'phones-tablets'
  | 'computers-office'
  | 'consumer-electronics'
  | 'electronics'
  | 'home-appliances'
  | 'professional-audio'
  | 'automotive'
  | 'health-medical'
  | 'jewelry-watches'
  | 'sports-outdoors'
  | 'industrial-tools'
  | 'pet-supplies'
  | 'groceries'
  | 'beauty'
  | 'furniture'
  | 'building-materials'
  | 'home-kitchen'
  | 'home-care'
  | 'kids-baby'
  | 'generic';

export type CategoryPresentation = {
  /** Owned local artwork — never remote random images. */
  artwork: ImageSourcePropType;
  artworkKey: CategoryArtworkKey;
  /** Filename under assets/images/categories/ */
  filename: string;
};

export type ResolvedCategoryImageSource = {
  source: ImageSourcePropType | { uri: string };
  kind: 'remote' | 'bundled';
  uri: string | null;
  artworkKey: CategoryArtworkKey | null;
  filename: string | null;
};

/** DepartmentSeeder names via Laravel Str::slug — presentation coverage only. */
export const CHINA_IMPORT_DEPARTMENT_SLUGS = [
  'mens-fashion',
  'womens-fashion',
  'phones-tablets',
  'computers-office',
  'consumer-electronics',
  'home-appliances',
  'home-furniture',
  'home-care',
  'beauty-personal-care',
  'health-medical',
  'jewelry-watches',
  'sports-outdoors',
  'automotive',
  'industrial-tools',
  'toys-kids',
  'pet-supplies',
  'groceries',
  'professional-audio',
] as const;

const ARTWORK: Record<CategoryArtworkKey, ImageSourcePropType> = {
  'womens-fashion': require('../../../../assets/images/categories/womens-fashion.png'),
  'mens-fashion': require('../../../../assets/images/categories/mens-fashion.png'),
  'phones-tablets': require('../../../../assets/images/categories/phones-tablets.png'),
  'computers-office': require('../../../../assets/images/categories/computers-office.png'),
  'consumer-electronics': require('../../../../assets/images/categories/electronics.png'),
  electronics: require('../../../../assets/images/categories/electronics.png'),
  'home-appliances': require('../../../../assets/images/categories/home-appliances.png'),
  'professional-audio': require('../../../../assets/images/categories/professional-audio.png'),
  automotive: require('../../../../assets/images/categories/automotive.png'),
  'health-medical': require('../../../../assets/images/categories/health-medical.png'),
  'jewelry-watches': require('../../../../assets/images/categories/jewelry-watches.png'),
  'sports-outdoors': require('../../../../assets/images/categories/sports-outdoors.png'),
  'industrial-tools': require('../../../../assets/images/categories/industrial-tools.png'),
  'pet-supplies': require('../../../../assets/images/categories/pet-supplies.png'),
  groceries: require('../../../../assets/images/categories/groceries.png'),
  beauty: require('../../../../assets/images/categories/beauty.png'),
  furniture: require('../../../../assets/images/categories/furniture.png'),
  'building-materials': require('../../../../assets/images/categories/building-materials.png'),
  'home-kitchen': require('../../../../assets/images/categories/home-kitchen.png'),
  'home-care': require('../../../../assets/images/categories/home-care.png'),
  'kids-baby': require('../../../../assets/images/categories/kids-baby.png'),
  generic: require('../../../../assets/images/categories/generic.png'),
};

const ARTWORK_FILENAME: Record<CategoryArtworkKey, string> = {
  'womens-fashion': 'womens-fashion.png',
  'mens-fashion': 'mens-fashion.png',
  'phones-tablets': 'phones-tablets.png',
  'computers-office': 'computers-office.png',
  'consumer-electronics': 'electronics.png',
  electronics: 'electronics.png',
  'home-appliances': 'home-appliances.png',
  'professional-audio': 'professional-audio.png',
  automotive: 'automotive.png',
  'health-medical': 'health-medical.png',
  'jewelry-watches': 'jewelry-watches.png',
  'sports-outdoors': 'sports-outdoors.png',
  'industrial-tools': 'industrial-tools.png',
  'pet-supplies': 'pet-supplies.png',
  groceries: 'groceries.png',
  beauty: 'beauty.png',
  furniture: 'furniture.png',
  'building-materials': 'building-materials.png',
  'home-kitchen': 'home-kitchen.png',
  'home-care': 'home-care.png',
  'kids-baby': 'kids-baby.png',
  generic: 'generic.png',
};

/** Exact slug → artwork. CMS/storefront aliases included without rewriting taxonomy. */
const SLUG_TO_ARTWORK: Record<string, CategoryArtworkKey> = {
  'womens-fashion': 'womens-fashion',
  'mens-fashion': 'mens-fashion',
  'phones-tablets': 'phones-tablets',
  'computers-office': 'computers-office',
  'consumer-electronics': 'consumer-electronics',
  electronics: 'electronics',
  'home-appliances': 'home-appliances',
  'professional-audio': 'professional-audio',
  automotive: 'automotive',
  'health-medical': 'health-medical',
  'jewelry-watches': 'jewelry-watches',
  'sports-outdoors': 'sports-outdoors',
  'industrial-tools': 'industrial-tools',
  'pet-supplies': 'pet-supplies',
  groceries: 'groceries',
  beauty: 'beauty',
  'beauty-personal-care': 'beauty',
  furniture: 'furniture',
  'home-furniture': 'furniture',
  'building-materials': 'building-materials',
  'home-kitchen': 'home-kitchen',
  'home-care': 'home-care',
  'kids-baby': 'kids-baby',
  'toys-kids': 'kids-baby',
};

/**
 * Child/department family prefixes (longest first).
 * `automotive-car-accessories` → automotive, never generic, never index-based.
 */
const SLUG_FAMILY_PREFIXES: (readonly [string, CategoryArtworkKey])[] = [
  ['beauty-personal-care', 'beauty'],
  ['consumer-electronics', 'consumer-electronics'],
  ['professional-audio', 'professional-audio'],
  ['computers-office', 'computers-office'],
  ['home-appliances', 'home-appliances'],
  ['jewelry-watches', 'jewelry-watches'],
  ['sports-outdoors', 'sports-outdoors'],
  ['industrial-tools', 'industrial-tools'],
  ['phones-tablets', 'phones-tablets'],
  ['health-medical', 'health-medical'],
  ['building-materials', 'building-materials'],
  ['womens-fashion', 'womens-fashion'],
  ['mens-fashion', 'mens-fashion'],
  ['home-furniture', 'furniture'],
  ['pet-supplies', 'pet-supplies'],
  ['home-kitchen', 'home-kitchen'],
  ['toys-kids', 'kids-baby'],
  ['home-care', 'home-care'],
  ['kids-baby', 'kids-baby'],
  ['automotive', 'automotive'],
  ['electronics', 'electronics'],
  ['furniture', 'furniture'],
  ['groceries', 'groceries'],
  ['beauty', 'beauty'],
];

function presentationForKey(key: CategoryArtworkKey): CategoryPresentation {
  return {
    artwork: ARTWORK[key],
    artworkKey: key,
    filename: ARTWORK_FILENAME[key],
  };
}

function tokensFromName(name: string): Set<string> {
  return new Set(
    name
      .toLowerCase()
      .replace(/&/g, ' ')
      .split(/[^a-z0-9]+/)
      .filter((token) => token.length > 0),
  );
}

function hasToken(tokens: Set<string>, ...needles: string[]): boolean {
  return needles.some((needle) => tokens.has(needle));
}

function artworkKeyFromSlug(slug: string): CategoryArtworkKey | null {
  const exact = SLUG_TO_ARTWORK[slug];
  if (exact) {
    return exact;
  }
  for (const [prefix, key] of SLUG_FAMILY_PREFIXES) {
    if (slug === prefix || slug.startsWith(`${prefix}-`)) {
      return key;
    }
  }
  return null;
}

function artworkKeyFromName(name: string): CategoryArtworkKey | null {
  const tokens = tokensFromName(name);
  if (tokens.size === 0) {
    return null;
  }

  if (hasToken(tokens, 'women', 'womens')) return 'womens-fashion';
  if (hasToken(tokens, 'men', 'mens') && !hasToken(tokens, 'women', 'womens')) {
    return 'mens-fashion';
  }
  if (hasToken(tokens, 'health', 'medical', 'pharmacy', 'clinical')) {
    return 'health-medical';
  }
  if (hasToken(tokens, 'automotive', 'vehicle', 'vehicles', 'automobile', 'car')) {
    return 'automotive';
  }
  if (hasToken(tokens, 'jewelry', 'jewellery', 'jewel')) return 'jewelry-watches';
  if (hasToken(tokens, 'watch', 'watches') && !hasToken(tokens, 'smart')) {
    return 'jewelry-watches';
  }
  if (hasToken(tokens, 'sport', 'sports', 'outdoor', 'outdoors', 'fitness')) {
    return 'sports-outdoors';
  }
  if (hasToken(tokens, 'pet', 'pets')) return 'pet-supplies';
  if (hasToken(tokens, 'grocery', 'groceries')) return 'groceries';
  if (hasToken(tokens, 'beauty', 'cosmetic', 'cosmetics')) return 'beauty';
  if (hasToken(tokens, 'furniture')) return 'furniture';
  if (hasToken(tokens, 'building')) return 'building-materials';
  if (
    hasToken(tokens, 'cleaning') ||
    hasToken(tokens, 'disinfectant') ||
    (hasToken(tokens, 'home') && hasToken(tokens, 'care'))
  ) {
    return 'home-care';
  }
  if (hasToken(tokens, 'appliance', 'appliances')) return 'home-appliances';
  if (hasToken(tokens, 'kitchen', 'cookware')) return 'home-kitchen';
  if (hasToken(tokens, 'kid', 'kids', 'baby', 'toy', 'toys')) return 'kids-baby';
  if (hasToken(tokens, 'phone', 'phones', 'tablet', 'tablets', 'smartphone')) {
    return 'phones-tablets';
  }
  if (hasToken(tokens, 'computer', 'computers', 'laptop', 'laptops', 'desktop')) {
    return 'computers-office';
  }
  if (hasToken(tokens, 'audio', 'musical', 'microphone')) return 'professional-audio';
  if (hasToken(tokens, 'industrial') || hasToken(tokens, 'tools', 'hardware', 'machinery')) {
    return 'industrial-tools';
  }
  if (hasToken(tokens, 'electronics', 'electron')) return 'consumer-electronics';

  return null;
}

export function resolveCategoryArtworkKey(input: {
  slug: string;
  name?: string | null;
}): CategoryArtworkKey {
  const slug = input.slug.trim().toLowerCase();
  return (
    artworkKeyFromSlug(slug) ??
    artworkKeyFromName(input.name ?? '') ??
    'generic'
  );
}

export function resolveCategoryPresentation(input: {
  slug: string;
  name?: string | null;
}): CategoryPresentation {
  return presentationForKey(resolveCategoryArtworkKey(input));
}

export function isExplicitCategoryImageUrl(
  imageUrl: string | null | undefined,
): imageUrl is string {
  const value = imageUrl?.trim() ?? '';
  if (value.length === 0) return false;
  if (value === 'null' || value === 'undefined') return false;
  return true;
}

/**
 * Deterministic tile source. Bundled artwork never overrides a valid CMS/backend URL.
 */
export function resolveCategoryImageSource(input: {
  slug: string;
  name?: string | null;
  imageUrl?: string | null;
}): ResolvedCategoryImageSource {
  if (isExplicitCategoryImageUrl(input.imageUrl)) {
    const uri = input.imageUrl.trim();
    return {
      source: { uri },
      kind: 'remote',
      uri,
      artworkKey: null,
      filename: null,
    };
  }

  const presentation = resolveCategoryPresentation(input);
  return {
    source: presentation.artwork,
    kind: 'bundled',
    uri: null,
    artworkKey: presentation.artworkKey,
    filename: presentation.filename,
  };
}

/** Required owned asset inventory for ops / designers. */
export const CATEGORY_ARTWORK_ASSET_MANIFEST = [
  {
    key: 'womens-fashion',
    filename: 'womens-fashion.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'dress + handbag + heels + scarf',
  },
  {
    key: 'mens-fashion',
    filename: 'mens-fashion.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'blazer + belt + watch + dress shoes',
  },
  {
    key: 'phones-tablets',
    filename: 'phones-tablets.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'smartphone + tablet (no earbuds/watch)',
  },
  {
    key: 'computers-office',
    filename: 'computers-office.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'laptop + desktop monitor + keyboard',
  },
  {
    key: 'electronics',
    filename: 'electronics.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'phone + earbuds + tablet + watch (consumer electronics family)',
  },
  {
    key: 'home-appliances',
    filename: 'home-appliances.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'kettle + blender (household appliances, not cookware)',
  },
  {
    key: 'professional-audio',
    filename: 'professional-audio.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'studio microphone + mixer + headphones',
  },
  {
    key: 'automotive',
    filename: 'automotive.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'passenger car + tire/wheel (vehicle context)',
  },
  {
    key: 'health-medical',
    filename: 'health-medical.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'stethoscope + BP monitor + thermometer (not electronics lifestyle)',
  },
  {
    key: 'jewelry-watches',
    filename: 'jewelry-watches.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'necklace + rings + analog watch',
  },
  {
    key: 'sports-outdoors',
    filename: 'sports-outdoors.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'soccer ball + dumbbell + running shoe',
  },
  {
    key: 'industrial-tools',
    filename: 'industrial-tools.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'power drill + wrench + toolbox',
  },
  {
    key: 'pet-supplies',
    filename: 'pet-supplies.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'pet bowl + toy + leash',
  },
  {
    key: 'groceries',
    filename: 'groceries.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'produce + oil bottle + pantry sack (no labels)',
  },
  {
    key: 'beauty',
    filename: 'beauty.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'perfume + lipstick + compact + brush',
  },
  {
    key: 'furniture',
    filename: 'furniture.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'vase + throw + lamp + bowl',
  },
  {
    key: 'building-materials',
    filename: 'building-materials.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'wood samples + hardware + level + tiles',
  },
  {
    key: 'home-kitchen',
    filename: 'home-kitchen.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'cookware + board + knife + towel',
  },
  {
    key: 'home-care',
    filename: 'home-care.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept:
      'cleaning spray + toilet cleaner + disinfectant wipes + sponge/cloth + brush + pest-control (no cookware)',
  },
  {
    key: 'kids-baby',
    filename: 'kids-baby.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'plush + wooden toy + blanket + baby shoes',
  },
  {
    key: 'generic',
    filename: 'generic.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'premium retail still-life fallback (no emoji/letters)',
  },
] as const;
