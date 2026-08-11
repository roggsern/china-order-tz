import { canAddToCart, resolveAddToCartGate } from './canAddToCart';
import type {
  CatalogProductDetail,
  ProductConfiguration,
} from '../models/types';

const product: CatalogProductDetail = {
  id: 'p1',
  slug: 'widget',
  name: 'Widget',
  price: 1000,
  imageUrl: null,
  images: [],
  variants: [],
  isPurchasable: true,
  availabilityStatus: 'available',
  inStock: true,
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

const incompleteConfig: ProductConfiguration = {
  productId: 'p1',
  hasConfigurations: true,
  isComplete: false,
  isInStock: true,
  matchedConfigurationId: null,
  matchedUnitPrice: null,
  attributes: [],
  configurations: [],
  allowedValueIds: {},
  capabilities: {},
  isPurchasable: true,
};

const matchedConfig: ProductConfiguration = {
  ...incompleteConfig,
  isComplete: true,
  matchedConfigurationId: 'cfg-1',
  isPurchasable: true,
  isInStock: true,
};

describe('resolveAddToCartGate', () => {
  it('loading disables ATC with Checking availability...', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: null,
      configurationLoading: true,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Checking availability...');
  });

  it('missing selection disables ATC with Select options', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: incompleteConfig,
      configurationLoading: false,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Select options');
  });

  it('server unavailable disables ATC', () => {
    const unavailable: ProductConfiguration = {
      ...matchedConfig,
      isPurchasable: false,
      isInStock: false,
    };
    const gate = resolveAddToCartGate({
      product,
      configuration: unavailable,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Unavailable');
  });

  it('configuration request failure disables ATC', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: null,
      configurationError: true,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Unavailable');
  });

  it('null configuration (not yet loaded) disables ATC', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: null,
      configurationLoading: false,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Checking availability...');
  });

  it('valid configuration enables ATC', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: matchedConfig,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(true);
    expect(gate.label).toBe('Add to cart');
  });

  it('change option / fetching disables ATC and ignores old match', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: matchedConfig,
      configurationLoading: true,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Checking availability...');
  });

  it('failed config disables ATC even if prior match existed', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: matchedConfig,
      configurationError: true,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Unavailable');
  });

  it('new match after fetch enables ATC', () => {
    const loading = resolveAddToCartGate({
      product,
      configuration: {
        ...matchedConfig,
        matchedConfigurationId: null,
        isComplete: false,
      },
      configurationLoading: true,
      quantity: 1,
    });
    expect(loading.canAdd).toBe(false);

    const ready = resolveAddToCartGate({
      product,
      configuration: matchedConfig,
      configurationLoading: false,
      configurationError: false,
      quantity: 1,
    });
    expect(ready.canAdd).toBe(true);
    expect(ready.label).toBe('Add to cart');
  });

  it('valid configuration enables ATC even when aggregate availability_status is out_of_stock', () => {
    const gate = resolveAddToCartGate({
      product: {
        ...product,
        availabilityStatus: 'out_of_stock',
        inStock: false,
      },
      configuration: {
        ...matchedConfig,
        availabilityStatus: 'out_of_stock',
        isPurchasable: true,
        isInStock: true,
      },
      quantity: 1,
    });
    expect(gate.canAdd).toBe(true);
    expect(gate.label).toBe('Add to cart');
  });

  it('matched configuration out of stock disables ATC', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: {
        ...matchedConfig,
        isPurchasable: true,
        isInStock: false,
        availabilityStatus: 'out_of_stock',
      },
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Unavailable');
  });

  it('simple product with server config enables ATC', () => {
    const gate = resolveAddToCartGate({
      product,
      configuration: simpleConfig,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(true);
    expect(gate.label).toBe('Add to cart');
  });

  it('product marked unavailable disables ATC for simple products', () => {
    const gate = resolveAddToCartGate({
      product: { ...product, isPurchasable: false },
      configuration: simpleConfig,
      quantity: 1,
    });
    expect(gate.canAdd).toBe(false);
    expect(gate.label).toBe('Unavailable');
  });
});

describe('canAddToCart', () => {
  it('requires a matched configuration for configured products', () => {
    expect(
      canAddToCart({ product, configuration: incompleteConfig, quantity: 1 }),
    ).toBe(false);
    expect(
      canAddToCart({ product, configuration: matchedConfig, quantity: 1 }),
    ).toBe(true);
  });

  it('disables while submitting or when quantity is invalid', () => {
    expect(
      canAddToCart({
        product,
        configuration: simpleConfig,
        quantity: 1,
        submitting: true,
      }),
    ).toBe(false);
    expect(canAddToCart({ product, configuration: simpleConfig, quantity: 0 })).toBe(
      false,
    );
  });
});
