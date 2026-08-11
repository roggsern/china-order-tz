import type { CommerceJourney } from '@/src/shared/types/commerce';
import type {
  HomepageHeroSlide,
  HomepageTrustItem,
} from '../models/types';

/**
 * Presentation-only hero slides (aligned with web seed commercial copy).
 * No product IDs, prices, or inventory — CTA targets Browse for the active journey.
 */
export function buildPresentationHeroSlides(
  journey: CommerceJourney,
): HomepageHeroSlide[] {
  if (journey === 'TZ_LOCAL') {
    return [
      {
        id: 'presentation-hero-tz',
        headline: 'Buy from Tanzania',
        subheadline: 'Curated Tanzanian Marketplace',
        eyebrow_text: 'Buy from TZ',
        description:
          'Fashion, beauty, jewelry, accessories, and lifestyle — trusted local stores across Tanzania.',
        primary_cta: {
          type: 'route',
          label: 'Explore TZ Stores',
          value: 'browse',
        },
        position: 0,
      },
    ];
  }

  return [
    {
      id: 'presentation-hero-china',
      headline: 'Order from China',
      subheadline: 'Global Import · China to Tanzania',
      eyebrow_text: 'Order from China',
      description:
        'Premium products sourced in China — air and sea logistics with trusted delivery to Tanzania.',
      primary_cta: {
        type: 'route',
        label: 'Explore China Catalog',
        value: 'browse',
      },
      position: 0,
    },
  ];
}

/** Presentation trust rows — same authority as web homepage seed copy. */
export function buildPresentationTrustItems(): HomepageTrustItem[] {
  return [
    {
      id: 'trust-secure',
      title: 'Secure Checkout',
      description: 'Protected payments on every order.',
    },
    {
      id: 'trust-delivery',
      title: 'Reliable Delivery',
      description: 'Tracked fulfillment to Tanzania.',
    },
    {
      id: 'trust-support',
      title: 'Customer Support',
      description: 'Real help for real shoppers.',
    },
    {
      id: 'trust-quality',
      title: 'Carefully Selected',
      description: 'Catalog curation across China imports and TZ stores.',
    },
  ];
}
