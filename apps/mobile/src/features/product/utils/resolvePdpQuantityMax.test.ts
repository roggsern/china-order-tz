import {
  canIncreasePurchaseQuantity,
  resolvePdpQuantityMax,
  sellablePurchaseMax,
} from './resolvePdpQuantityMax';
import type { ProductConfiguration } from '../models/types';

const simpleAvailable = {
  stock: 250,
};

const matchedConfig = (stock: number | null): ProductConfiguration => ({
  productId: 'p1',
  hasConfigurations: true,
  isComplete: true,
  isInStock: stock == null ? true : stock > 0,
  matchedConfigurationId: 'cfg-1',
  matchedUnitPrice: 23000,
  attributes: [],
  configurations: [
    {
      id: 'cfg-1',
      attributeValueIds: ['color-red'],
      stock,
      inStock: stock == null ? true : stock > 0,
    },
  ],
  allowedValueIds: {},
  capabilities: {},
  isPurchasable: true,
});

describe('resolvePdpQuantityMax', () => {
  it('does not default to 99 and uses available stock 250', () => {
    expect(sellablePurchaseMax(undefined)).toBe(0);
    expect(resolvePdpQuantityMax({ product: simpleAvailable })).toBe(250);
    expect(canIncreasePurchaseQuantity(99, 250)).toBe(true);
    expect(canIncreasePurchaseQuantity(100, 250)).toBe(true);
    expect(canIncreasePurchaseQuantity(250, 250)).toBe(false);
  });

  it('uses matched configuration stock for configurable products', () => {
    expect(
      resolvePdpQuantityMax({
        product: { stock: null },
        configuration: matchedConfig(250),
      }),
    ).toBe(250);
    expect(
      resolvePdpQuantityMax({
        product: { stock: 999 },
        configuration: matchedConfig(5),
      }),
    ).toBe(5);
  });

  it('is fail-closed when stock is missing, zero, or configuration is incomplete', () => {
    expect(resolvePdpQuantityMax({ product: { stock: null } })).toBe(0);
    expect(resolvePdpQuantityMax({ product: { stock: 0 } })).toBe(0);
    expect(
      resolvePdpQuantityMax({
        product: { stock: 250 },
        configuration: {
          ...matchedConfig(250),
          isComplete: false,
          matchedConfigurationId: null,
        },
      }),
    ).toBe(0);
    expect(
      resolvePdpQuantityMax({
        product: { stock: null },
        configuration: matchedConfig(null),
      }),
    ).toBe(0);
  });
});
