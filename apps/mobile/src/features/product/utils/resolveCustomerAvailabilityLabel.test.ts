import { resolveCustomerAvailabilityLabel } from './resolveCustomerAvailabilityLabel';
import { resolveAddToCartGate } from './canAddToCart';
import { resolvePdpAvailabilityKind } from './resolvePdpAvailability';
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

const incompleteConfig: ProductConfiguration = {
  productId: 'p1',
  hasConfigurations: true,
  isComplete: false,
  isInStock: false,
  matchedConfigurationId: null,
  matchedUnitPrice: null,
  attributes: [],
  configurations: [],
  allowedValueIds: {},
  capabilities: {},
  availabilityStatus: 'out_of_stock',
  isPurchasable: true,
};

const matchedInStock: ProductConfiguration = {
  productId: 'p1',
  hasConfigurations: true,
  isComplete: true,
  isInStock: true,
  matchedConfigurationId: 'cfg-1',
  matchedUnitPrice: '25000',
  attributes: [],
  configurations: [],
  allowedValueIds: {},
  capabilities: {},
  // Aggregate product status on the configuration payload (API merge).
  availabilityStatus: 'out_of_stock',
  isPurchasable: true,
};

const matchedOutOfStock: ProductConfiguration = {
  ...matchedInStock,
  isInStock: false,
  availabilityStatus: 'out_of_stock',
};

const matchedUnavailable: ProductConfiguration = {
  ...matchedInStock,
  isPurchasable: false,
  isInStock: false,
  availabilityStatus: 'unavailable',
};

const simpleConfig: ProductConfiguration = {
  productId: 'p1',
  hasConfigurations: false,
  isComplete: true,
  isInStock: true,
  matchedConfigurationId: null,
  matchedUnitPrice: null,
  attributes: [],
  configurations: [],
  allowedValueIds: {},
  capabilities: {},
  isPurchasable: true,
};

describe('resolveCustomerAvailabilityLabel / Wave 0 sell-unit truth', () => {
  it('asks customers to select options when configuration incomplete', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product,
        configuration: incompleteConfig,
      }),
    ).toBe('Select options');
  });

  it('shows Available for matched in-stock sell unit even when aggregate status is out_of_stock', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product: {
          ...product,
          availabilityStatus: 'out_of_stock',
          inStock: false,
        },
        configuration: matchedInStock,
      }),
    ).toBe('Available');
  });

  it('does not use product.inStock when a configuration match exists', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product: { ...product, inStock: false, availabilityStatus: 'available' },
        configuration: matchedInStock,
      }),
    ).toBe('Available');
  });

  it('shows Out of stock when matched sell unit is not in stock', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product,
        configuration: matchedOutOfStock,
      }),
    ).toBe('Out of stock');
  });

  it('shows Unavailable when matched configuration is not purchasable', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product,
        configuration: matchedUnavailable,
      }),
    ).toBe('Unavailable');
  });

  it('shows Available when server says available and purchasable (simple / no config)', () => {
    expect(
      resolveCustomerAvailabilityLabel({ product, configuration: null }),
    ).toBe('Available');
  });

  it('shows Out of stock for simple products from product flags', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product: { ...product, isPurchasable: true, inStock: false },
        configuration: simpleConfig,
      }),
    ).toBe('Out of stock');
  });

  it('shows Out of stock for simple products from availability_status', () => {
    expect(
      resolveCustomerAvailabilityLabel({
        product: {
          ...product,
          availabilityStatus: 'out_of_stock',
          inStock: true,
        },
        configuration: simpleConfig,
      }),
    ).toBe('Out of stock');
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

describe('badge + ATC alignment (Wave 0)', () => {
  it('aggregate OOS + matched in stock → Available and ATC enabled', () => {
    const params = {
      product: {
        ...product,
        availabilityStatus: 'out_of_stock' as const,
        inStock: false,
      },
      configuration: matchedInStock,
    };
    expect(resolveCustomerAvailabilityLabel(params)).toBe('Available');
    const gate = resolveAddToCartGate({ ...params, quantity: 1 });
    expect(gate.canAdd).toBe(true);
    expect(gate.label).toBe('Add to cart');
    expect(resolvePdpAvailabilityKind(params)).toBe('available');
  });

  it('matched out of stock → Out of stock and ATC blocked', () => {
    const params = { product, configuration: matchedOutOfStock };
    expect(resolveCustomerAvailabilityLabel(params)).toBe('Out of stock');
    const gate = resolveAddToCartGate({ ...params, quantity: 1 });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Unavailable');
  });

  it('no valid match → Select options and ATC blocked', () => {
    const params = { product, configuration: incompleteConfig };
    expect(resolveCustomerAvailabilityLabel(params)).toBe('Select options');
    const gate = resolveAddToCartGate({ ...params, quantity: 1 });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Select options');
  });

  it('simple product OOS remains blocked consistently', () => {
    const params = {
      product: {
        ...product,
        availabilityStatus: 'out_of_stock' as const,
        inStock: false,
      },
      configuration: simpleConfig,
    };
    expect(resolveCustomerAvailabilityLabel(params)).toBe('Out of stock');
    const gate = resolveAddToCartGate({ ...params, quantity: 1 });
    expect(gate.canAdd).toBe(false);
  });

  it('availability kind is journey-agnostic (no China/TZ branching)', () => {
    const china = resolvePdpAvailabilityKind({
      product: { ...product, commerceChannelCode: 'CHINA_IMPORT' },
      configuration: matchedInStock,
    });
    const tz = resolvePdpAvailabilityKind({
      product: { ...product, commerceChannelCode: 'TZ_LOCAL' },
      configuration: matchedInStock,
    });
    expect(china).toBe('available');
    expect(tz).toBe('available');
    expect(china).toBe(tz);
  });
});
