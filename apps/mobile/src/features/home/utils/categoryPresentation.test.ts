import {
  CATEGORY_ARTWORK_ASSET_MANIFEST,
  resolveCategoryArtworkKey,
  resolveCategoryPresentation,
} from './categoryPresentation';

describe('categoryPresentation artwork registry', () => {
  it('maps department/storefront slugs to owned artwork keys', () => {
    expect(resolveCategoryArtworkKey({ slug: 'womens-fashion' })).toBe(
      'womens-fashion',
    );
    expect(resolveCategoryArtworkKey({ slug: 'electronics' })).toBe(
      'electronics',
    );
    expect(resolveCategoryArtworkKey({ slug: 'phones-tablets' })).toBe(
      'electronics',
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
    expect(filenames).toEqual([
      'womens-fashion.png',
      'mens-fashion.png',
      'electronics.png',
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
