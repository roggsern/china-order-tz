import {
  isCatalogSalePrice,
  resolvePlpAvailability,
} from './resolvePlpAvailability';

describe('resolvePlpAvailability', () => {
  it('marks simple available products as available (no badge)', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: 'available',
        isPurchasable: true,
        inStock: true,
        commerceChannelCode: 'CHINA_IMPORT',
      }),
    ).toEqual({ kind: 'available', badgeLabel: null });
  });

  it('marks explicit out_of_stock as OOS', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: 'out_of_stock',
        isPurchasable: true,
        inStock: false,
        commerceChannelCode: 'TZ_LOCAL',
      }),
    ).toEqual({ kind: 'out_of_stock', badgeLabel: 'Out of stock' });
  });

  it('marks unavailable / not purchasable as Unavailable', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: 'unavailable',
        isPurchasable: false,
      }),
    ).toEqual({ kind: 'unavailable', badgeLabel: 'Unavailable' });

    expect(
      resolvePlpAvailability({
        availabilityStatus: 'available',
        isPurchasable: false,
      }),
    ).toEqual({ kind: 'unavailable', badgeLabel: 'Unavailable' });
  });

  it('does not false-OOS configurable aggregate when status is available but inStock is false', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: 'available',
        isPurchasable: true,
        inStock: false,
        commerceChannelCode: 'CHINA_IMPORT',
      }),
    ).toEqual({ kind: 'available', badgeLabel: null });

    expect(
      resolvePlpAvailability({
        availabilityStatus: 'available',
        isPurchasable: true,
        inStock: false,
        commerceChannelCode: 'TZ_LOCAL',
      }),
    ).toEqual({ kind: 'available', badgeLabel: null });
  });

  it('China softens missing status + inStock false (no invented OOS)', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: null,
        inStock: false,
        commerceChannelCode: 'CHINA_IMPORT',
      }),
    ).toEqual({ kind: 'available', badgeLabel: null });
  });

  it('TZ uses inStock false when status is missing', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: null,
        inStock: false,
        commerceChannelCode: 'TZ_LOCAL',
      }),
    ).toEqual({ kind: 'out_of_stock', badgeLabel: 'Out of stock' });
  });

  it('isolates China vs TZ softening only for missing status', () => {
    expect(
      resolvePlpAvailability({
        availabilityStatus: 'out_of_stock',
        inStock: false,
        commerceChannelCode: 'CHINA_IMPORT',
      }).kind,
    ).toBe('out_of_stock');
  });
});

describe('isCatalogSalePrice', () => {
  it('is true only when compare-at is greater than price', () => {
    expect(isCatalogSalePrice('1000', '1500')).toBe(true);
    expect(isCatalogSalePrice(1000, 1000)).toBe(false);
    expect(isCatalogSalePrice('2000', '1500')).toBe(false);
    expect(isCatalogSalePrice(null, '1500')).toBe(false);
    expect(isCatalogSalePrice('1000', null)).toBe(false);
  });
});
