import {
  composeHomepageViewModel,
  emptyCmsHomepageView,
  filterSectionsForJourney,
} from './composeHomepage';
import type { LiveHomepageCommerce } from '../api/fetchLiveHomepageCommerce';
import type {
  HomepageViewModel,
  RenderableHomepageSection,
} from '../models/types';

const emptyLive: LiveHomepageCommerce = {
  categories: [],
  stores: [],
  featuredProducts: [],
  catalogProducts: [],
};

function cmsWithSections(
  sections: RenderableHomepageSection[],
  overrides?: Partial<HomepageViewModel>,
): HomepageViewModel {
  return {
    layout: {
      id: 'lay',
      name: 'Layout',
      slug: 'layout',
      commerce_context: 'CHINA_IMPORT',
      status: 'active',
      is_default: true,
      sections: [],
    },
    meta: {
      commerce_context: 'CHINA_IMPORT',
      resolved_commerce_context: 'CHINA_IMPORT',
      allow_global_fallback: true,
      used_global_fallback: false,
      campaign: null,
    },
    sections,
    ...overrides,
  };
}

describe('composeHomepageViewModel', () => {
  it('fills TZ_LOCAL empty CMS with live stores + products + presentation hero', () => {
    const live: LiveHomepageCommerce = {
      categories: [],
      stores: [
        {
          id: 's1',
          name: 'Dar Store',
          slug: 'dar-store',
          description: null,
          imageUrl: null,
        },
      ],
      featuredProducts: [
        {
          id: 'p1',
          slug: 'dress',
          name: 'Dress',
          price: '10000',
          imageUrl: null,
          commerceChannelCode: 'TZ_LOCAL',
          storeSlug: 'dar-store',
        },
      ],
      catalogProducts: [],
    };

    const composed = composeHomepageViewModel({
      cms: emptyCmsHomepageView('TZ_LOCAL'),
      live,
      journey: 'TZ_LOCAL',
    });

    expect(composed.sections.map((s) => s.kind)).toEqual([
      'HERO',
      'SHOP_BY_STORE',
      'FEATURED_PRODUCTS',
      'TRUST',
    ]);
    const hero = composed.sections.find((s) => s.kind === 'HERO');
    expect(hero?.kind === 'HERO' && hero.slides[0]?.headline).toBe(
      'Buy from Tanzania',
    );
    const stores = composed.sections.find((s) => s.kind === 'SHOP_BY_STORE');
    expect(stores?.kind === 'SHOP_BY_STORE' && stores.stores[0]?.slug).toBe(
      'dar-store',
    );
  });

  it('fills CHINA_IMPORT empty CMS with live categories + products', () => {
    const live: LiveHomepageCommerce = {
      categories: [
        {
          id: 'c1',
          name: 'Electronics',
          slug: 'electronics',
          description: null,
          imageUrl: null,
        },
      ],
      stores: [
        {
          id: 's1',
          name: 'Should not appear',
          slug: 'leak',
          description: null,
          imageUrl: null,
        },
      ],
      featuredProducts: [],
      catalogProducts: [
        {
          id: 'p1',
          slug: 'phone',
          name: 'Phone',
          price: '1',
          imageUrl: null,
          commerceChannelCode: 'CHINA_IMPORT',
        },
      ],
    };

    const composed = composeHomepageViewModel({
      cms: emptyCmsHomepageView('CHINA_IMPORT'),
      live,
      journey: 'CHINA_IMPORT',
    });

    expect(composed.sections.map((s) => s.kind)).toEqual([
      'HERO',
      'FEATURED_CATEGORIES',
      'NEW_ARRIVALS',
      'TRUST',
    ]);
    expect(
      composed.sections.some((s) => s.kind === 'SHOP_BY_STORE'),
    ).toBe(false);
  });

  it('keeps CMS hero and does not inject presentation hero', () => {
    const cms = cmsWithSections([
      {
        kind: 'HERO',
        key: 'cms-hero',
        title: null,
        subtitle: null,
        slides: [
          {
            id: 'slide',
            headline: 'CMS Headline',
            subheadline: null,
            position: 0,
          },
        ],
      },
      {
        kind: 'FEATURED_PRODUCTS',
        key: 'cms-feat',
        title: 'Featured',
        subtitle: null,
        products: [
          {
            id: 'p1',
            slug: 'a',
            name: 'A',
            price: 1,
            imageUrl: null,
            commerceChannelCode: 'CHINA_IMPORT',
          },
        ],
      },
    ]);

    const composed = composeHomepageViewModel({
      cms,
      live: emptyLive,
      journey: 'CHINA_IMPORT',
    });

    const heroes = composed.sections.filter((s) => s.kind === 'HERO');
    expect(heroes).toHaveLength(1);
    expect(heroes[0]?.kind === 'HERO' && heroes[0].slides[0]?.headline).toBe(
      'CMS Headline',
    );
  });

  it('strips TZ stores from CHINA_IMPORT and filters GLOBAL mixed products', () => {
    const sections: RenderableHomepageSection[] = [
      {
        kind: 'SHOP_BY_STORE',
        key: 'stores',
        title: 'Stores',
        subtitle: null,
        stores: [
          {
            id: 's1',
            name: 'Store',
            slug: 'store',
            description: null,
            imageUrl: null,
          },
        ],
      },
      {
        kind: 'FEATURED_PRODUCTS',
        key: 'feat',
        title: 'Feat',
        subtitle: null,
        products: [
          {
            id: 'china',
            slug: 'c',
            name: 'China',
            price: 1,
            imageUrl: null,
            commerceChannelCode: 'CHINA_IMPORT',
          },
          {
            id: 'tz',
            slug: 't',
            name: 'TZ',
            price: 1,
            imageUrl: null,
            commerceChannelCode: 'TZ_LOCAL',
          },
        ],
      },
    ];

    const filtered = filterSectionsForJourney(
      sections,
      'CHINA_IMPORT',
      {
        commerce_context: 'GLOBAL',
        used_global_fallback: true,
      },
      'GLOBAL',
    );

    expect(filtered.map((s) => s.kind)).toEqual(['FEATURED_PRODUCTS']);
    const feat = filtered[0];
    expect(feat?.kind === 'FEATURED_PRODUCTS' && feat.products.map((p) => p.id)).toEqual([
      'china',
    ]);
  });

  it('strips GLOBAL category rails on TZ_LOCAL', () => {
    const filtered = filterSectionsForJourney(
      [
        {
          kind: 'FEATURED_CATEGORIES',
          key: 'cats',
          title: 'Cats',
          subtitle: null,
          categories: [
            {
              id: 'c1',
              name: 'China Cat',
              slug: 'china-cat',
              description: null,
              imageUrl: null,
            },
          ],
        },
      ],
      'TZ_LOCAL',
      { commerce_context: 'GLOBAL', used_global_fallback: true },
      'GLOBAL',
    );
    expect(filtered).toEqual([]);
  });
});
