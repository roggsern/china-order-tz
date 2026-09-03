import type {
  CatalogProductDetail,
  ProductConfiguration,
} from '../models/types';

/**
 * Purchase quantity ceiling is available sellable stock.
 * Unknown / missing / non-positive stock is 0 (fail closed, not 99 or unlimited).
 */
export function sellablePurchaseMax(stock: number | null | undefined): number {
  if (typeof stock !== 'number' || !Number.isFinite(stock) || stock <= 0) {
    return 0;
  }
  return Math.floor(stock);
}

export function canIncreasePurchaseQuantity(quantity: number, max: number): boolean {
  return quantity < max;
}

/**
 * PDP + max from the matched sell unit.
 * Configurable: matched configuration row stock.
 * Simple: product-level stock from the detail API.
 * Incomplete / unknown: 0.
 */
export function resolvePdpQuantityMax(params: {
  product?: Pick<CatalogProductDetail, 'stock'> | null;
  configuration?: ProductConfiguration | null;
}): number {
  const { product, configuration } = params;

  if (configuration?.hasConfigurations) {
    if (!configuration.isComplete || !configuration.matchedConfigurationId) {
      return 0;
    }
    const matched = configuration.configurations.find(
      (row) => row.id === configuration.matchedConfigurationId,
    );
    return sellablePurchaseMax(matched?.stock);
  }

  return sellablePurchaseMax(product?.stock);
}
