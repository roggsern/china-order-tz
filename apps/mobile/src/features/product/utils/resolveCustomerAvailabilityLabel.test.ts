import { resolveCustomerAvailabilityLabel } from './resolveCustomerAvailabilityLabel';
import type { CatalogProductDetail, ProductConfiguration } from '../models/types';

const product: CatalogProductDetail = {
  id: 'p1',
  slug: 'item',
  name: 'Item',
  price: '25000',
  imageUrl: null,
  images: [],
  variants: [],
  availabilityStatus: 'available',
  inStock: true,
  isPurchasable: true,
};

const baseConfig: ProductConfiguration = {
  productId: 'p1',
  hasConfigurations: true,
  isComplete: false,
  isInStock: false,
  matchedConfigurationId: null,
  matchedUnitPrice: null,
  attributes: [],
  allowedValueIds: {},
  capabilities: {},
  availabilityStatus: 'unavailable',
  isPurchasable: false,
};

describe('resolveCustomerAvailabilityLabel', () => {
  it('asks customers to select options when configuration incomplete', () => {
    expect(resolveCustomerAvailabilityLabel({ product, configuration: baseConfig })).toBe(
      'Select options',
    );
  });

  it('shows Out of stock when config complete but not in stock', () => {
    const configuration: ProductConfiguration = {
      ...baseConfig,
      isComplete: true,
      isPurchasable: false,
      isInStock: false,
      availabilityStatus: 'out_of_stock',
    };
    expect(resolveCustomerAvailabilityLabel({ product, configuration })).toBe(
      'Out of stock',
    );
  });

  it('shows Available when server says available and purchasable', () => {
    expect(resolveCustomerAvailabilityLabel({ product, configuration: null })).toBe(
      'Available',
    );
  });

  it('does not expose conflicting technical flag strings', () => {
    const label = resolveCustomerAvailabilityLabel({
      product: { ...product, isPurchasable: true, inStock: false },
      configuration: null,
    });
    expect(label).not.toMatch(/Purchasable/i);
    expect(label).not.toMatch(/Configuration in stock/i);
    expect(label).toBe('Out of stock');
  });
});
