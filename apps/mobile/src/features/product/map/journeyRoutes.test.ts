import {
  buildProductHref,
  chinaProductsPath,
  parseJourneyParam,
  resolveProductDetailPath,
  tzStoreProductDetailPath,
  tzStoreProductsPath,
} from './journeyRoutes';

describe('journey routing', () => {
  it('keeps CHINA_IMPORT and TZ_LOCAL endpoints separated', () => {
    expect(chinaProductsPath()).toBe('/storefront/china/products');
    expect(tzStoreProductsPath('dar-store')).toBe(
      '/storefront/tz/stores/dar-store/products',
    );
    expect(tzStoreProductDetailPath('dar-store', 'widget')).toBe(
      '/storefront/tz/stores/dar-store/products/widget',
    );
  });

  it('resolves product detail paths by journey', () => {
    expect(
      resolveProductDetailPath({
        journey: 'CHINA_IMPORT',
        productKey: 'widget',
      }),
    ).toBe('/products/widget');

    expect(
      resolveProductDetailPath({
        journey: 'TZ_LOCAL',
        productKey: 'widget',
        storeSlug: 'dar-store',
      }),
    ).toBe('/storefront/tz/stores/dar-store/products/widget');
  });

  it('requires storeSlug for TZ_LOCAL detail routing', () => {
    expect(() =>
      resolveProductDetailPath({
        journey: 'TZ_LOCAL',
        productKey: 'widget',
      }),
    ).toThrow(/storeSlug/);
  });

  it('builds Home → Product Detail hrefs with journey query', () => {
    expect(
      buildProductHref({
        slug: 'widget',
        journey: 'CHINA_IMPORT',
      }),
    ).toBe('/(app)/product/widget?journey=CHINA_IMPORT');

    expect(
      buildProductHref({
        slug: 'widget',
        journey: 'TZ_LOCAL',
        storeSlug: 'dar-store',
      }),
    ).toBe('/(app)/product/widget?journey=TZ_LOCAL&store=dar-store');
  });

  it('parses journey query params without renaming backend values', () => {
    expect(parseJourneyParam('TZ_LOCAL', 'CHINA_IMPORT')).toBe('TZ_LOCAL');
    expect(parseJourneyParam('nope', 'CHINA_IMPORT')).toBe('CHINA_IMPORT');
  });
});
