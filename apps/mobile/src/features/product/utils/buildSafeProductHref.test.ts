import {
  browseCatalogKind,
  buildSafeProductHref,
  resolveOwnedTzStoreSlug,
  TZ_STORE_REQUIRED_MESSAGE,
} from './buildSafeProductHref';

describe('browseCatalogKind', () => {
  it('opens China catalog for CHINA_IMPORT journey', () => {
    expect(browseCatalogKind('CHINA_IMPORT')).toBe('china');
  });

  it('opens TZ catalog for TZ_LOCAL journey', () => {
    expect(browseCatalogKind('TZ_LOCAL')).toBe('tz');
  });
});

describe('buildSafeProductHref', () => {
  it('China products open without store', () => {
    const result = buildSafeProductHref({
      slug: 'widget',
      journey: 'CHINA_IMPORT',
    });
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.href).toContain('journey=CHINA_IMPORT');
      expect(result.href).not.toContain('store=');
    }
  });

  it('TZ home product with store opens PDP successfully', () => {
    const result = buildSafeProductHref({
      slug: 'kitenge',
      journey: 'TZ_LOCAL',
      productStoreSlug: 'zion',
    });
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.href).toContain('journey=TZ_LOCAL');
      expect(result.href).toContain('store=zion');
    }
  });

  it('TZ home product without store fails closed', () => {
    const result = buildSafeProductHref({
      slug: 'kitenge',
      journey: 'TZ_LOCAL',
    });
    expect(result).toEqual({
      ok: false,
      message: TZ_STORE_REQUIRED_MESSAGE,
    });
  });

  it('selected/first store cannot silently substitute for product store', () => {
    const result = buildSafeProductHref({
      slug: 'kitenge',
      journey: 'TZ_LOCAL',
      productStoreSlug: null,
      // Intentionally omit browseScopedStoreSlug — selected UI store must not apply.
    });
    expect(result.ok).toBe(false);
  });

  it('Browse-scoped store is allowed only when products were fetched in that store', () => {
    const result = buildSafeProductHref({
      slug: 'kitenge',
      journey: 'TZ_LOCAL',
      browseScopedStoreSlug: 'dar-central',
    });
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.storeSlug).toBe('dar-central');
    }
  });

  it('product-owned store wins over browse-scoped store', () => {
    const result = buildSafeProductHref({
      slug: 'kitenge',
      journey: 'TZ_LOCAL',
      productStoreSlug: 'from-product',
      browseScopedStoreSlug: 'from-browse',
    });
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.storeSlug).toBe('from-product');
    }
  });

  it('China navigation unaffected by missing store', () => {
    const result = buildSafeProductHref({
      slug: 'import-item',
      journey: 'CHINA_IMPORT',
      productStoreSlug: null,
    });
    expect(result.ok).toBe(true);
  });

  it('TZ search hit with store navigates', () => {
    const result = buildSafeProductHref({
      slug: 'tz-hit',
      journey: 'TZ_LOCAL',
      productStoreSlug: 'store-a',
    });
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.href).toContain('store=store-a');
    }
  });

  it('TZ search hit without store fails closed', () => {
    const result = buildSafeProductHref({
      slug: 'tz-hit',
      journey: 'TZ_LOCAL',
      productStoreSlug: null,
    });
    expect(result.ok).toBe(false);
  });

  it('TZ deep link with store succeeds (same href builder)', () => {
    const result = buildSafeProductHref({
      slug: 'deep',
      journey: 'TZ_LOCAL',
      productStoreSlug: 'owned-store',
    });
    expect(result.ok).toBe(true);
  });

  it('TZ deep link without store fails closed', () => {
    expect(
      buildSafeProductHref({
        slug: 'deep',
        journey: 'TZ_LOCAL',
      }).ok,
    ).toBe(false);
  });
});

describe('resolveOwnedTzStoreSlug', () => {
  it('returns product store only', () => {
    expect(resolveOwnedTzStoreSlug({ productStoreSlug: 'zion' })).toBe('zion');
  });

  it('does not invent stores', () => {
    expect(resolveOwnedTzStoreSlug({})).toBeNull();
  });
});
