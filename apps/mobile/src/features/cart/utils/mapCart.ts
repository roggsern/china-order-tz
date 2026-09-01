import type {
  AddToCartInput,
  AddToCartPayload,
  Cart,
  CartDisplayAttribute,
  CartItem,
  CartSummary,
  UpdateCartItemPayload,
} from '../models/types';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import { mapVolumePricing } from '@/src/features/pricing/mapVolumePricing';
import {
  mapPurchaseQuantity,
  mapPurchaseQuantityBlockers,
} from '@/src/features/purchasing/purchaseQuantity';
import { journeyLabelFromChannel } from './journeyLabel';
import { preferStorefrontImageSrcFromUnknown } from '@/src/shared/media/preferStorefrontImageSrc';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function numberField(data: Record<string, unknown>, key: string): number | null {
  const value = data[key];
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function moneyField(data: Record<string, unknown>, key: string): string | number | null {
  const value = data[key];
  if (typeof value === 'string' || typeof value === 'number') {
    return value;
  }
  return null;
}

function mediaUrl(media: unknown): string | null {
  return preferStorefrontImageSrcFromUnknown(media);
}

function mapDisplayAttributes(raw: unknown): CartDisplayAttribute[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((row) => {
      const item = asRecord(row);
      const attribute = stringField(item, 'attribute');
      const value = stringField(item, 'value');
      if (!attribute || !value) return null;
      return { attribute, value };
    })
    .filter((row): row is CartDisplayAttribute => row !== null);
}

/**
 * Contract v1 add-to-cart body.
 * Simple product → product_id + quantity.
 * Variant / configuration product → product_variant_id + quantity (product_id kept for server validation).
 */
export function buildAddToCartPayload(input: AddToCartInput): AddToCartPayload {
  const quantity = Math.max(1, Math.floor(Number(input.quantity) || 1));
  const variantId = input.productVariantId?.trim() || null;
  const shipping =
    input.shippingMethod === 'air' || input.shippingMethod === 'sea'
      ? input.shippingMethod
      : undefined;

  if (variantId) {
    return {
      product_id: input.productId,
      product_variant_id: variantId,
      quantity,
      ...(shipping ? { shipping_method: shipping } : {}),
    };
  }

  return {
    product_id: input.productId,
    quantity,
    ...(shipping ? { shipping_method: shipping } : {}),
  };
}

/** PATCH/PUT /cart/items/{id} body — quantity only; server prices the line. */
export function buildUpdateCartItemPayload(quantity: number): UpdateCartItemPayload {
  return {
    quantity: Math.max(1, Math.floor(Number(quantity) || 1)),
  };
}

export function mapCartItem(raw: unknown): CartItem | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const productId = stringField(data, 'product_id');
  if (!id || !productId) return null;

  const product = asRecord(data.product);
  const variant = data.variant ? asRecord(data.variant) : {};
  const quantity = numberField(data, 'quantity') ?? 0;
  const commerceChannelCode =
    stringField(product, 'commerce_channel_code') ??
    stringField(data, 'commerce_channel_code');

  const displayAttributes = mapDisplayAttributes(variant.display_attributes);
  const variantName = stringField(variant, 'name');
  const variantSku = stringField(variant, 'sku');

  return {
    id,
    productId,
    productVariantId: stringField(data, 'product_variant_id'),
    quantity,
    unitPrice: moneyField(data, 'unit_price') ?? moneyField(data, 'price_snapshot'),
    lineSubtotal: moneyField(data, 'subtotal'),
    currency: stringField(data, 'currency') ?? 'TZS',
    availableStock: numberField(data, 'available_stock'),
    shippingMethod: stringField(data, 'shipping_method'),
    shippingPrice: moneyField(data, 'shipping_price'),
    productName: stringField(product, 'name') ?? 'Product',
    productSlug: stringField(product, 'slug'),
    imageUrl:
      mediaUrl(variant.primary_image) ??
      mediaUrl(product.primary_image) ??
      null,
    commerceChannelCode,
    commerceSourceLabel: stringField(product, 'commerce_source_label'),
    journeyLabel: journeyLabelFromChannel(commerceChannelCode),
    variantName,
    variantSku,
    displayAttributes,
    volumePricing: mapVolumePricing(data.volume_pricing),
    purchaseQuantity: mapPurchaseQuantity(data.purchase_quantity),
  };
}

/** Map full Contract v1 CartResource — totals/line prices come from the server. */
export function mapCart(raw: unknown): Cart {
  const data = asRecord(raw);
  const itemsRaw = Array.isArray(data.items) ? data.items : [];
  const items = itemsRaw
    .map(mapCartItem)
    .filter((item): item is CartItem => item !== null);

  const itemCount = numberField(data, 'item_count');
  const isEmpty =
    typeof data.is_empty === 'boolean' ? data.is_empty : items.length === 0;

  return {
    id: stringField(data, 'id'),
    status: stringField(data, 'status'),
    currency: stringField(data, 'currency') ?? 'TZS',
    items,
    itemCount: itemCount ?? items.length,
    isEmpty,
    subtotal: moneyField(data, 'subtotal'),
    total: moneyField(data, 'total'),
    purchaseQuantityBlockers: mapPurchaseQuantityBlockers(
      data.purchase_quantity_blockers,
    ),
  };
}

export function mapCartSummary(raw: unknown): CartSummary {
  const cart = mapCart(raw);
  return {
    id: cart.id,
    status: cart.status,
    currency: cart.currency,
    itemCount: cart.itemCount,
    isEmpty: cart.isEmpty,
    subtotal: cart.subtotal,
    total: cart.total,
  };
}

export function formatCartMoney(
  value: string | number | null | undefined,
  currency = 'TZS',
): string {
  return formatCustomerMoney(value, currency);
}
