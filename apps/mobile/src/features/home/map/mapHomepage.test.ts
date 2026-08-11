import { ApiError } from '@/src/core/errors';
import { useJourneyStore } from '@/src/core/auth/journeyStore';
import {
  buildRenderableSections,
  homepageQueryKey,
  mapHomepageResponse,
  mapProductCard,
  resolveHomepageCommerceContext,
} from './mapHomepage';
import type { HomepageLayout, HomepageMeta } from '../models/types';

const baseMeta: HomepageMeta = {
  commerce_context: 'CHINA_IMPORT',
  resolved_commerce_context: 'CHINA_IMPORT',
  allow_global_fallback: true,
  used_global_fallback: false,
  campaign: null,
};

function layoutWithSections(
  sections: HomepageLayout['sections'],
): HomepageLayout {
  return {
    id: 'lay_1',
    name: 'China Home',
    slug: 'china-home',
    commerce_context: 'CHINA_IMPORT',
    status: 'active',
    is_default: true,
    sections,
  };
}

describe('mapHomepageResponse', () => {
  it('maps Contract v1 success envelope into a view model', () => {
    const view = mapHomepageResponse({
      success: true,
      data: layoutWithSections([
        {
          id: 'sec_hero',
          section_type: 'HERO',
          title: 'Hero',
          subtitle: null,
          position: 1,
          is_visible: true,
          hero_slides: [
            {
              id: 'slide_1',
              headline: 'Ship from China',
              subheadline: 'Factory direct',
              position: 0,
            },
          ],
        },
        {
          id: 'sec_feat',
          section_type: 'FEATURED_PRODUCTS',
          title: 'Featured',
          subtitle: null,
          position: 2,
          is_visible: true,
          featured_contents: [
            {
              id: 'fc_1',
              title: null,
              subtitle: null,
              position: 0,
              items: [
                {
                  item_type: 'PRODUCT',
                  id: 'p1',
                  data: {
                    id: 'p1',
                    slug: 'widget',
                    name: 'Widget',
                    price: '12000',
                    primary_image: { url: 'https://cdn.example/w.jpg' },
                  },
                },
              ],
            },
          ],
        },
      ]),
      meta: {
        ...baseMeta,
        campaign: {
          id: 'camp_1',
          name: 'Spring Sale',
          slug: 'spring-sale',
          priority: 10,
        },
      },
    });

    expect(view.layout?.slug).toBe('china-home');
    expect(view.sections.map((s) => s.kind)).toEqual([
      'HERO',
      'CAMPAIGN',
      'FEATURED_PRODUCTS',
    ]);
    const featured = view.sections.find((s) => s.kind === 'FEATURED_PRODUCTS');
    expect(featured?.kind === 'FEATURED_PRODUCTS' && featured.products[0]?.name).toBe(
      'Widget',
    );
  });

  it('allows null data with meta message', () => {
    const view = mapHomepageResponse({
      success: true,
      data: null,
      meta: {
        commerce_context: 'TZ_LOCAL',
        message: 'No active campaign or default homepage layout for this context.',
      },
    });

    expect(view.layout).toBeNull();
    expect(view.sections).toEqual([]);
    expect(view.meta.message).toMatch(/No active/);
  });

  it('maps Contract v1 failure envelope to ApiError', () => {
    expect(() =>
      mapHomepageResponse({
        success: false,
        code: 'maintenance_mode',
        message: 'Down for maintenance',
        errors: {},
      }),
    ).toThrow(ApiError);

    try {
      mapHomepageResponse({
        success: false,
        code: 'maintenance_mode',
        message: 'Down for maintenance',
      });
    } catch (error) {
      expect(error).toMatchObject({ code: 'maintenance_mode' });
    }
  });
});

describe('buildRenderableSections', () => {
  it('ignores unknown section types and hidden sections', () => {
    const sections = buildRenderableSections(
      layoutWithSections([
        {
          id: 'sec_hidden',
          section_type: 'FEATURED_PRODUCTS',
          title: 'Hidden',
          subtitle: null,
          position: 0,
          is_visible: false,
          featured_contents: [],
        },
        {
          id: 'sec_trust',
          section_type: 'TRUST_INDICATORS',
          title: 'Trust',
          subtitle: null,
          position: 1,
          is_visible: true,
        },
        {
          id: 'sec_new',
          section_type: 'NEW_ARRIVALS',
          title: 'New',
          subtitle: null,
          position: 2,
          is_visible: true,
          featured_contents: [
            {
              id: 'fc_new',
              title: null,
              subtitle: null,
              position: 0,
              items: [
                {
                  item_type: 'PRODUCT',
                  id: 'p2',
                  data: { id: 'p2', name: 'Arrival', slug: 'arrival', price: 1 },
                },
              ],
            },
          ],
        },
        {
          id: 'sec_best',
          section_type: 'BEST_SELLERS',
          title: 'Best',
          subtitle: null,
          position: 3,
          is_visible: true,
          featured_contents: [],
        },
      ]),
      baseMeta,
    );

    expect(sections.map((s) => s.kind)).toEqual(['NEW_ARRIVALS', 'BEST_SELLERS']);
  });
});

describe('mapProductCard', () => {
  it('does not invent products without id/name', () => {
    expect(mapProductCard({})).toBeNull();
    expect(mapProductCard({ id: 'x' })).toBeNull();
  });

  it('maps store slug from CMS when present and never invents one', () => {
    expect(
      mapProductCard({
        id: 'p1',
        name: 'Shirt',
        slug: 'shirt',
        store: { slug: 'zion' },
      })?.storeSlug,
    ).toBe('zion');
    expect(
      mapProductCard({
        id: 'p2',
        name: 'Shirt',
        slug: 'shirt',
      })?.storeSlug,
    ).toBeNull();
  });
});

describe('journey context switching', () => {
  beforeEach(() => {
    useJourneyStore.setState({ journey: 'CHINA_IMPORT' });
  });

  it('maps journey values to homepage commerce_context without renaming', () => {
    expect(resolveHomepageCommerceContext('CHINA_IMPORT')).toBe('CHINA_IMPORT');
    expect(resolveHomepageCommerceContext('TZ_LOCAL')).toBe('TZ_LOCAL');
    expect(homepageQueryKey('GLOBAL')).toEqual(['storefront', 'homepage', 'GLOBAL']);
  });

  it('switches query context when journey changes', () => {
    expect(homepageQueryKey(resolveHomepageCommerceContext(useJourneyStore.getState().journey))).toEqual([
      'storefront',
      'homepage',
      'CHINA_IMPORT',
    ]);

    useJourneyStore.getState().setJourney('TZ_LOCAL');

    expect(homepageQueryKey(resolveHomepageCommerceContext(useJourneyStore.getState().journey))).toEqual([
      'storefront',
      'homepage',
      'TZ_LOCAL',
    ]);
  });
});
