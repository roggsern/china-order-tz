import type { ImageSourcePropType } from 'react-native';

/**
 * Owned Shop-by-Category artwork registry.
 * Taxonomy/slugs remain API-owned — this file is presentation only.
 *
 * Artwork files live in `apps/mobile/assets/images/categories/`
 * Aspect: 1:1 · Recommended master: 1024×1024+ · No text in artwork.
 */

export type CategoryArtworkKey =
  | 'womens-fashion'
  | 'mens-fashion'
  | 'electronics'
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

const ARTWORK: Record<CategoryArtworkKey, ImageSourcePropType> = {
  'womens-fashion': require('../../../../assets/images/categories/womens-fashion.png'),
  'mens-fashion': require('../../../../assets/images/categories/mens-fashion.png'),
  electronics: require('../../../../assets/images/categories/electronics.png'),
  beauty: require('../../../../assets/images/categories/beauty.png'),
  furniture: require('../../../../assets/images/categories/furniture.png'),
  'building-materials': require('../../../../assets/images/categories/building-materials.png'),
  'home-kitchen': require('../../../../assets/images/categories/home-kitchen.png'),
  'home-care': require('../../../../assets/images/categories/home-care.png'),
  'kids-baby': require('../../../../assets/images/categories/kids-baby.png'),
  generic: require('../../../../assets/images/categories/generic.png'),
};

/** Canonical storefront / homepage presentation slug → artwork. */
const SLUG_TO_ARTWORK: Record<string, CategoryArtworkKey> = {
  'womens-fashion': 'womens-fashion',
  'mens-fashion': 'mens-fashion',
  electronics: 'electronics',
  beauty: 'beauty',
  furniture: 'furniture',
  'building-materials': 'building-materials',
  'home-kitchen': 'home-kitchen',
  'kids-baby': 'kids-baby',
  // CatalogBible / CMS — Home Care is cleaning/care, NOT kitchen cookware.
  'home-care': 'home-care',
  // DepartmentSeeder soft aliases (presentation only)
  'phones-tablets': 'electronics',
  'computers-office': 'electronics',
  'consumer-electronics': 'electronics',
  'professional-audio': 'electronics',
  'home-appliances': 'home-kitchen',
  'home-furniture': 'furniture',
  'beauty-personal-care': 'beauty',
  'toys-kids': 'kids-baby',
};

function presentationForKey(key: CategoryArtworkKey): CategoryPresentation {
  return {
    artwork: ARTWORK[key],
    artworkKey: key,
    filename: `${key}.png`,
  };
}

export function resolveCategoryArtworkKey(input: {
  slug: string;
  name?: string | null;
}): CategoryArtworkKey {
  const slug = input.slug.trim().toLowerCase();
  if (SLUG_TO_ARTWORK[slug]) {
    return SLUG_TO_ARTWORK[slug]!;
  }

  const name = (input.name ?? '').toLowerCase();
  if (name.includes('women')) return 'womens-fashion';
  if (name.includes('men')) return 'mens-fashion';
  if (
    name.includes('electron') ||
    name.includes('phone') ||
    name.includes('computer') ||
    name.includes('audio')
  ) {
    return 'electronics';
  }
  if (name.includes('beauty') || name.includes('cosmetic')) return 'beauty';
  if (name.includes('furniture')) return 'furniture';
  if (name.includes('build') || name.includes('material')) {
    return 'building-materials';
  }
  // Home Care before generic "home" so cleaning departments stay semantic.
  if (
    name.includes('home care') ||
    name.includes('cleaning') ||
    name.includes('disinfectant') ||
    name.includes('pest')
  ) {
    return 'home-care';
  }
  if (name.includes('kitchen') || name.includes('appliance')) {
    return 'home-kitchen';
  }
  if (name.includes('kid') || name.includes('baby') || name.includes('toy')) {
    return 'kids-baby';
  }

  return 'generic';
}

export function resolveCategoryPresentation(input: {
  slug: string;
  name?: string | null;
}): CategoryPresentation {
  return presentationForKey(resolveCategoryArtworkKey(input));
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
    key: 'electronics',
    filename: 'electronics.png',
    aspectRatio: '1:1',
    recommendedPx: '1024x1024',
    concept: 'phone + earbuds + tablet + watch',
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
