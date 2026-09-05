import { sameConfigurationQuotePlaceholder } from './useCatalogQueries';

describe('sameConfigurationQuotePlaceholder', () => {
  const redKey = ['catalog', 'quote', 'widget', 'cfg-red', 1] as const;
  const redQty2 = ['catalog', 'quote', 'widget', 'cfg-red', 2] as const;
  const blueKey = ['catalog', 'quote', 'widget', 'cfg-blue', 1] as const;
  const otherProduct = ['catalog', 'quote', 'gadget', 'simple', 1] as const;

  it('reuses the previous quote when only quantity changes', () => {
    expect(
      sameConfigurationQuotePlaceholder(
        { unitPrice: '10000.00' },
        { queryKey: redKey },
        { productKey: 'widget', configurationKey: 'cfg-red' },
      ),
    ).toEqual({ unitPrice: '10000.00' });
    expect(redQty2[3]).toBe('cfg-red');
  });

  it('drops stale data when the variant or product changes', () => {
    expect(
      sameConfigurationQuotePlaceholder(
        { unitPrice: '10000.00' },
        { queryKey: redKey },
        { productKey: 'widget', configurationKey: 'cfg-blue' },
      ),
    ).toBeUndefined();
    expect(
      sameConfigurationQuotePlaceholder(
        { unitPrice: '10000.00' },
        { queryKey: otherProduct },
        { productKey: 'widget', configurationKey: 'simple' },
      ),
    ).toBeUndefined();
    expect(blueKey[3]).toBe('cfg-blue');
  });
});
