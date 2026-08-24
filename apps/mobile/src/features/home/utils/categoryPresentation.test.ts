import {
  CATEGORY_ARTWORK_ASSET_MANIFEST,
  CHINA_IMPORT_DEPARTMENT_SLUGS,
  resolveCategoryArtworkKey,
  resolveCategoryImageSource,
  resolveCategoryPresentation,
} from './categoryPresentation';
import { mapCategoryCard } from '../map/mapHomepage';

describe('categoryPresentation artwork registry', () => {
  it('maps department/storefront slugs to owned artwork keys', () => {
    expect(resolveCategoryArtworkKey({ slug: 'womens-fashion' })).toBe(
      'womens-fashion',
    );
    expect(resolveCategoryArtworkKey({ slug: 'electronics' })).toBe(
      'electronics',
    );
    expect(resolveCategoryArtworkKey({ slug: 'phones-tablets' })).toBe(
      'phones-tablets',
    );
  });

  it('maps Home Care to cleaning artwork — never kitchen cookware', () => {
    expect(resolveCategoryArtworkKey({ slug: 'home-care' })).toBe('home-care');
    expect(
      resolveCategoryArtworkKey({ slug: 'x', name: 'Home Care' }),
    ).toBe('home-care');
    expect(resolveCategoryPresentation({ slug: 'home-care' }).filename).toBe(
      'home-care.png',
    );
    expect(resolveCategoryArtworkKey({ slug: 'home-kitchen' })).toBe(
      'home-kitchen',
    );
  });

  it('uses premium generic fallback — never emoji/letter keys', () => {
    const presentation = resolveCategoryPresentation({
      slug: 'unknown-dept',
    });
    expect(presentation.artworkKey).toBe('generic');
    expect(presentation.filename).toBe('generic.png');
    expect(presentation).not.toHaveProperty('icon');
  });

  it('exposes required asset filenames without mutating taxonomy', () => {
    const filenames = CATEGORY_ARTWORK_ASSET_MANIFEST.map((row) => row.filename);
    expect(filenames).toContain('home-care.png');
    expect(filenames).toContain('home-kitchen.png');
    expect(filenames).toContain('automotive.png');
    expect(filenames).toContain('health-medical.png');
    expect(filenames).toEqual([
      'womens-fashion.png',
      'mens-fashion.png',
      'phones-tablets.png',
      'computers-office.png',
      'electronics.png',
      'home-appliances.png',
      'professional-audio.png',
      'automotive.png',
      'health-medical.png',
      'jewelry-watches.png',
      'sports-outdoors.png',
      'industrial-tools.png',
      'pet-supplies.png',
      'groceries.png',
      'beauty.png',
      'furniture.png',
      'building-materials.png',
      'home-kitchen.png',
      'home-care.png',
      'kids-baby.png',
      'generic.png',
    ]);
  });

  it('keeps deep-link slug identity when resolving presentation', () => {
    const key = resolveCategoryArtworkKey({
      slug: 'womens-fashion',
      name: "Women's Fashion",
    });
    expect(key).toBe('womens-fashion');
  });
});

describe('category image resolver — Wave 8E', () => {
  it('resolves Automotive to automotive-specific bundled artwork', () => {
    const resolved = resolveCategoryImageSource({
      slug: 'automotive',
      name: 'Automotive',
      imageUrl: null,
    });
    expect(resolved.kind).toBe('bundled');
    expect(resolved.artworkKey).toBe('automotive');
    expect(resolved.filename).toBe('automotive.png');
    expect(resolved.source).toBeDefined();
  });

  it('resolves Health & Medical to distinct health/medical artwork', () => {
    const resolved = resolveCategoryImageSource({
      slug: 'health-medical',
      name: 'Health & Medical',
      imageUrl: null,
    });
    expect(resolved.kind).toBe('bundled');
    expect(resolved.artworkKey).toBe('health-medical');
    expect(resolved.filename).toBe('health-medical.png');
    expect(resolved.source).toBeDefined();
  });

  it('keeps Automotive imagery different from Health & Medical', () => {
    const automotive = resolveCategoryImageSource({ slug: 'automotive' });
    const health = resolveCategoryImageSource({ slug: 'health-medical' });
    expect(automotive.artworkKey).not.toBe(health.artworkKey);
    expect(automotive.filename).not.toBe(health.filename);
    expect(automotive.source).not.toEqual(health.source);
  });

  it('lets an explicit CMS/backend image override bundled Automotive artwork', () => {
    const card = mapCategoryCard({
      id: 'dept-auto',
      name: 'Automotive',
      slug: 'automotive',
      image: 'https://cdn.example/custom-automotive.jpg',
    });
    expect(card?.imageUrl).toBe('https://cdn.example/custom-automotive.jpg');

    const resolved = resolveCategoryImageSource({
      slug: card!.slug,
      name: card!.name,
      imageUrl: card!.imageUrl,
    });
    expect(resolved.kind).toBe('remote');
    expect(resolved.uri).toBe('https://cdn.example/custom-automotive.jpg');
    expect(resolved.source).toEqual({
      uri: 'https://cdn.example/custom-automotive.jpg',
    });
    expect(resolved.artworkKey).toBeNull();
  });

  it('maps Women’s Fashion to the fashion family', () => {
    expect(
      resolveCategoryArtworkKey({
        slug: 'womens-fashion',
        name: "Women's Fashion",
      }),
    ).toBe('womens-fashion');
  });

  it('maps Consumer Electronics to the electronics family', () => {
    expect(
      resolveCategoryArtworkKey({
        slug: 'consumer-electronics',
        name: 'Consumer Electronics',
      }),
    ).toBe('consumer-electronics');
    expect(
      resolveCategoryPresentation({ slug: 'consumer-electronics' }).filename,
    ).toBe('electronics.png');
  });

  it('maps Computers & Office to office-technology artwork', () => {
    expect(
      resolveCategoryArtworkKey({
        slug: 'computers-office',
        name: 'Computers & Office',
      }),
    ).toBe('computers-office');
    expect(
      resolveCategoryPresentation({ slug: 'computers-office' }).filename,
    ).toBe('computers-office.png');
  });

  it('maps Home Appliances to appliance artwork — not cookware', () => {
    expect(
      resolveCategoryArtworkKey({
        slug: 'home-appliances',
        name: 'Home Appliances',
      }),
    ).toBe('home-appliances');
    expect(
      resolveCategoryArtworkKey({ slug: 'x', name: 'Home Appliances' }),
    ).toBe('home-appliances');
    expect(
      resolveCategoryArtworkKey({ slug: 'home-kitchen' }),
    ).toBe('home-kitchen');
  });

  it('uses the neutral generic fallback for unknown categories', () => {
    const resolved = resolveCategoryImageSource({
      slug: 'brand-new-admin-category',
      name: 'Something Entirely New',
      imageUrl: null,
    });
    expect(resolved.kind).toBe('bundled');
    expect(resolved.artworkKey).toBe('generic');
    expect(resolved.filename).toBe('generic.png');
  });

  it('never returns an undefined/broken source when image is missing', () => {
    const resolved = resolveCategoryImageSource({
      slug: 'missing-image-dept',
      name: null,
      imageUrl: '   ',
    });
    expect(resolved.source).toBeDefined();
    expect(resolved.kind).toBe('bundled');
    expect(resolved.uri).toBeNull();
  });

  it('maps by slug identity, not array index', () => {
    const automotiveAtZero = resolveCategoryArtworkKey({
      slug: 'automotive',
    });
    const healthAtZero = resolveCategoryArtworkKey({
      slug: 'health-medical',
    });
    expect(automotiveAtZero).toBe('automotive');
    expect(healthAtZero).toBe('health-medical');
    expect(automotiveAtZero).not.toBe(healthAtZero);
  });

  it('keeps the same imagery when categories are reordered', () => {
    const orderA = ['automotive', 'health-medical', 'womens-fashion'];
    const orderB = ['womens-fashion', 'health-medical', 'automotive'];
    const keysA = orderA.map((slug) => resolveCategoryArtworkKey({ slug }));
    const keysB = orderB.map((slug) => resolveCategoryArtworkKey({ slug }));
    expect(keysA[0]).toBe(keysB[2]);
    expect(keysA[1]).toBe(keysB[1]);
    expect(keysA[2]).toBe(keysB[0]);
  });

  it('does not share unrelated imagery for similar names', () => {
    expect(
      resolveCategoryArtworkKey({
        slug: 'health-medical',
        name: 'Health & Medical',
      }),
    ).not.toBe(
      resolveCategoryArtworkKey({ slug: 'home-care', name: 'Home Care' }),
    );
    expect(
      resolveCategoryArtworkKey({
        slug: 'automotive',
        name: 'Automotive',
      }),
    ).not.toBe(
      resolveCategoryArtworkKey({
        slug: 'industrial-tools',
        name: 'Industrial & Tools',
      }),
    );
    expect(
      resolveCategoryArtworkKey({
        slug: 'x',
        name: 'Car Accessories',
      }),
    ).toBe('automotive');
    expect(
      resolveCategoryArtworkKey({
        slug: 'x',
        name: 'Personal Care',
      }),
    ).not.toBe('automotive');
  });

  it('covers every CHINA_IMPORT department slug with a defined source', () => {
    for (const slug of CHINA_IMPORT_DEPARTMENT_SLUGS) {
      const resolved = resolveCategoryImageSource({ slug, imageUrl: null });
      expect(resolved.source).toBeDefined();
      expect(resolved.kind).toBe('bundled');
      expect(resolved.artworkKey).not.toBeNull();
      expect(resolved.filename).toMatch(/\.png$/);
    }
    expect(resolveCategoryArtworkKey({ slug: 'automotive' })).toBe('automotive');
    expect(resolveCategoryArtworkKey({ slug: 'health-medical' })).toBe(
      'health-medical',
    );
    expect(
      resolveCategoryArtworkKey({ slug: 'automotive-car-accessories' }),
    ).toBe('automotive');
    expect(
      resolveCategoryArtworkKey({ slug: 'health-medical-medical-equipment' }),
    ).toBe('health-medical');
  });

  it('falls back safely for TZ_LOCAL / unknown store categories', () => {
    const resolved = resolveCategoryImageSource({
      slug: 'mboga',
      name: 'Mboga',
      imageUrl: null,
    });
    expect(resolved.artworkKey).toBe('generic');
    expect(resolved.source).toBeDefined();
  });

  it('does not rewrite brand or store labels', () => {
    const brandName = 'ZION MODE';
    const resolved = resolveCategoryImageSource({
      slug: 'zion-mode',
      name: brandName,
    });
    expect(brandName).toBe('ZION MODE');
    expect(resolved.artworkKey).toBe('generic');
  });

  it('keeps browse navigation slug identical to the category identity', () => {
    const category = {
      id: 'c-auto',
      name: 'Automotive',
      slug: 'automotive',
    };
    resolveCategoryImageSource(category);
    expect(category.slug).toBe('automotive');
  });
});
