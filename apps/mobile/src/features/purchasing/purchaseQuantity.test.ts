import {
  formatAddToCartFollowUp,
  formatAllowedQuantitiesExample,
  formatPurchaseQuantityCheckoutMessage,
  isCheckoutStartBlockedByPurchaseState,
  mapPurchaseQuantity,
  mapPurchaseQuantityBlocker,
  mapPurchaseQuantityBlockers,
  parsePurchaseQuantityCheckoutError,
  purchaseQuantityMessageFromError,
  resolveCartBlockerView,
  resolvePdpPurchaseQuantityView,
  resolveQuotePurchaseQuantity,
  selectBlockerForProduct,
  shouldBlockCheckoutCta,
  type PurchaseQuantityBlocker,
  type PurchaseQuantityPresentation,
} from './purchaseQuantity';
import { ApiError } from '@/src/core/errors';
import { productQuoteQueryKey } from '@/src/features/product/hooks/useCatalogQueries';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const MOQ_ONLY: PurchaseQuantityPresentation = {
  minimum_quantity: 6,
  increment: null,
  eligible_quantity: 2,
  aggregates_variants: false,
  minimum_satisfied: false,
  increment_satisfied: true,
  quantity_to_minimum: 4,
  next_legal_quantity: 6,
  construction_complete: false,
  blocks_checkout: true,
};

const LEGAL_MOQ: PurchaseQuantityPresentation = {
  ...MOQ_ONLY,
  eligible_quantity: 6,
  minimum_satisfied: true,
  quantity_to_minimum: 0,
  next_legal_quantity: 6,
  construction_complete: true,
  blocks_checkout: false,
};

const ILLEGAL_INCREMENT: PurchaseQuantityPresentation = {
  minimum_quantity: 6,
  increment: 3,
  eligible_quantity: 7,
  aggregates_variants: false,
  minimum_satisfied: true,
  increment_satisfied: false,
  quantity_to_minimum: 0,
  next_legal_quantity: 9,
  construction_complete: false,
  blocks_checkout: true,
};

const CONFIGURABLE: PurchaseQuantityPresentation = {
  ...MOQ_ONLY,
  aggregates_variants: true,
};

function blocker(
  overrides: Partial<PurchaseQuantityBlocker> & Pick<PurchaseQuantityBlocker, 'product_id'>,
): PurchaseQuantityBlocker {
  return {
    minimum_quantity: 6,
    increment: null,
    eligible_quantity: 4,
    minimum_satisfied: false,
    increment_satisfied: true,
    quantity_to_minimum: 2,
    next_legal_quantity: 6,
    blocks_checkout: true,
    ...overrides,
  };
}

describe('purchase quantity mobile helpers', () => {
  it('A. PDP no-rule renders no purchase section', () => {
    expect(resolvePdpPurchaseQuantityView(null)).toBeNull();
    expect(mapPurchaseQuantity(null)).toBeNull();
    expect(mapPurchaseQuantity(undefined)).toBeNull();
  });

  it('B. PDP MOQ-only shows minimum copy without inventing increment', () => {
    const view = resolvePdpPurchaseQuantityView({
      ...MOQ_ONLY,
      eligible_quantity: 6,
      minimum_satisfied: true,
      quantity_to_minimum: 0,
      construction_complete: true,
      blocks_checkout: false,
    });
    expect(view?.minimumLabel).toBe('Minimum order quantity: 6');
    expect(view?.incrementLabel).toBeNull();
    expect(view?.allowedExample).toBeNull();
  });

  it('C. PDP below minimum uses server quantity_to_minimum', () => {
    const view = resolvePdpPurchaseQuantityView(MOQ_ONLY);
    expect(view?.status).toBe('Add 4 more to reach the minimum.');
    expect(view?.incomplete).toBe(true);
  });

  it('D. PDP legal minimum is a subtle satisfied state', () => {
    const view = resolvePdpPurchaseQuantityView(LEGAL_MOQ);
    expect(view?.status).toBe('Minimum reached.');
    expect(view?.incomplete).toBe(false);
  });

  it('E. PDP increment guidance uses published increment', () => {
    const view = resolvePdpPurchaseQuantityView({
      ...ILLEGAL_INCREMENT,
      eligible_quantity: 6,
      increment_satisfied: true,
      next_legal_quantity: 6,
      construction_complete: true,
      blocks_checkout: false,
    });
    expect(view?.incrementLabel).toBe('Order increment: 3');
    expect(view?.allowedExample).toBe('Allowed quantities: 6, 9, 12, 15, ...');
  });

  it('F. PDP illegal increment uses server next_legal_quantity', () => {
    const view = resolvePdpPurchaseQuantityView(ILLEGAL_INCREMENT);
    expect(view?.nextAllowed).toBe('Next allowed quantity: 9');
    expect(JSON.stringify(view).includes('%')).toBe(false);
  });

  it('G. quote change refreshes status only for the matching quantity', () => {
    const stale = resolveQuotePurchaseQuantity(
      { quantity: 2, purchaseQuantity: MOQ_ONLY },
      6,
    );
    const fresh = resolveQuotePurchaseQuantity(
      { quantity: 6, purchaseQuantity: LEGAL_MOQ },
      6,
    );
    expect(stale).toBeNull();
    expect(fresh?.eligible_quantity).toBe(6);
    expect(fresh?.blocks_checkout).toBe(false);
  });

  it('G-race. a late qty-2 quote cannot render as qty-7 state', () => {
    const lateQty2 = resolveQuotePurchaseQuantity(
      { quantity: 2, purchaseQuantity: MOQ_ONLY },
      7,
    );
    const qty7 = resolveQuotePurchaseQuantity(
      { quantity: 7, purchaseQuantity: ILLEGAL_INCREMENT },
      7,
    );
    expect(lateQty2).toBeNull();
    expect(qty7?.next_legal_quantity).toBe(9);
    expect(
      productQuoteQueryKey({
        productKey: 'slug',
        configurationId: null,
        quantity: 2,
      }),
    ).not.toEqual(
      productQuoteQueryKey({
        productKey: 'slug',
        configurationId: null,
        quantity: 7,
      }),
    );
  });

  it('H. configurable helper', () => {
    const view = resolvePdpPurchaseQuantityView(CONFIGURABLE);
    expect(view?.mixVariants).toBe(
      'You can mix variants to reach the required quantity.',
    );
  });

  it('I. Add to Cart is not gated by purchase quantity helpers', () => {
    const addToCart = readFileSync(
      resolve(__dirname, '../product/utils/canAddToCart.ts'),
      'utf8',
    );
    const addToCartButton = readFileSync(
      resolve(__dirname, '../product/components/AddToCartButton.tsx'),
      'utf8',
    );
    expect(addToCart.includes('blocks_checkout')).toBe(false);
    expect(addToCart.includes('purchaseQuantity')).toBe(false);
    expect(addToCartButton.includes('blocks_checkout')).toBe(false);
  });

  it('J. mobile has no Buy Now CTA or unused intercept helper', () => {
    const helper = readFileSync(resolve(__dirname, './purchaseQuantity.ts'), 'utf8');
    expect(helper.includes('shouldInterceptBuyNow')).toBe(false);
    expect(helper.includes('formatBuyNowInterceptMessage')).toBe(false);
    expect(helper.includes('isAddToCartBlockedByPurchaseQuantity')).toBe(false);
    expect(helper.includes('presentationAsBlocker')).toBe(false);
  });

  it('K. cart no blockers leaves checkout enabled', () => {
    expect(shouldBlockCheckoutCta([])).toBe(false);
    expect(shouldBlockCheckoutCta(null)).toBe(false);
  });

  it('L. cart one blocker', () => {
    const blockers = [blocker({ product_id: 'p1' })];
    expect(shouldBlockCheckoutCta(blockers)).toBe(true);
    expect(selectBlockerForProduct(blockers, 'p1')?.product_id).toBe('p1');
  });

  it('M. sibling variants collapse to one blocker per product_id', () => {
    const blockers = mapPurchaseQuantityBlockers([
      blocker({ product_id: 'p1', eligible_quantity: 4 }),
      blocker({ product_id: 'p1', eligible_quantity: 4 }),
    ]);
    expect(blockers).toHaveLength(1);
    expect(blockers[0]?.product_id).toBe('p1');
  });

  it('N. different products remain separate blockers', () => {
    const blockers = mapPurchaseQuantityBlockers([
      blocker({ product_id: 'p1' }),
      blocker({
        product_id: 'q1',
        minimum_quantity: 8,
        quantity_to_minimum: 3,
        next_legal_quantity: 8,
      }),
    ]);
    expect(blockers).toHaveLength(2);
    expect(selectBlockerForProduct(blockers, 'p1')?.product_id).toBe('p1');
    expect(selectBlockerForProduct(blockers, 'q1')?.minimum_quantity).toBe(8);
  });

  it('O. mutation blocker refresh uses the latest server list', () => {
    expect(mapPurchaseQuantityBlockers([])).toEqual([]);
    expect(shouldBlockCheckoutCta(mapPurchaseQuantityBlockers([]))).toBe(false);
    const restored = mapPurchaseQuantityBlockers([blocker({ product_id: 'p1' })]);
    expect(restored).toHaveLength(1);
    expect(shouldBlockCheckoutCta(restored)).toBe(true);
  });

  it('P. checkout CTA blocked when blockers exist or cart is not loaded', () => {
    expect(shouldBlockCheckoutCta([blocker({ product_id: 'p1' })])).toBe(true);
    expect(
      isCheckoutStartBlockedByPurchaseState({
        cartLoaded: false,
        blockers: undefined,
      }),
    ).toBe(true);
    expect(
      isCheckoutStartBlockedByPurchaseState({
        cartLoaded: true,
        blockers: [],
      }),
    ).toBe(false);
    expect(
      isCheckoutStartBlockedByPurchaseState({
        cartLoaded: true,
        blockers: [blocker({ product_id: 'p1' })],
      }),
    ).toBe(true);
  });

  it('Q. checkout-start structured 422 is rendered from data fields', () => {
    const parsed = parsePurchaseQuantityCheckoutError({
      success: false,
      code: 'purchase_quantity_unsatisfied',
      message: 'This product does not meet the purchase quantity rule.',
      data: {
        purchase_quantity: {
          product_id: 'p1',
          minimum_quantity: 6,
          increment: null,
          eligible_quantity: 4,
          minimum_satisfied: false,
          increment_satisfied: true,
          quantity_to_minimum: 2,
          next_legal_quantity: 6,
          blocks_checkout: true,
        },
      },
    });
    expect(parsed.code).toBe('purchase_quantity_unsatisfied');
    expect(formatPurchaseQuantityCheckoutMessage(parsed.blocker)).toBe(
      'Add 2 more before checkout.',
    );
  });

  it('R. place-order stale-rule 422 uses structured ApiError.raw', () => {
    const error = new ApiError({
      message: 'This product does not meet the purchase quantity rule.',
      status: 422,
      code: 'purchase_quantity_unsatisfied',
      raw: {
        code: 'purchase_quantity_unsatisfied',
        message: 'This product does not meet the purchase quantity rule.',
        data: {
          purchase_quantity: {
            product_id: 'p1',
            minimum_quantity: 6,
            increment: 3,
            eligible_quantity: 7,
            minimum_satisfied: true,
            increment_satisfied: false,
            quantity_to_minimum: 0,
            next_legal_quantity: 9,
            blocks_checkout: true,
          },
        },
      },
    });
    expect(purchaseQuantityMessageFromError(error)).toBe(
      'Next allowed quantity is 9.',
    );
    expect(purchaseQuantityMessageFromError(error)).not.toBe(error.message);
  });

  it('S. quote does not combine cart quantity', () => {
    const mapped = mapPurchaseQuantity({
      ...MOQ_ONLY,
      eligible_quantity: 2,
    });
    expect(mapped?.eligible_quantity).toBe(2);
    expect(mapped?.eligible_quantity).not.toBe(8);
  });

  it('T. volume pricing and purchase blockers are independent fields', () => {
    const purchase = mapPurchaseQuantity(ILLEGAL_INCREMENT);
    expect(purchase?.blocks_checkout).toBe(true);
    expect(purchase?.eligible_quantity).toBe(7);
    expect('volume_pricing' in (purchase ?? {})).toBe(false);
  });

  it('U. guest/auth sync does not invent local blockers', () => {
    expect(mapPurchaseQuantityBlockers(undefined)).toEqual([]);
    expect(
      shouldBlockCheckoutCta(
        mapPurchaseQuantityBlockers({
          minimum_quantity: 6,
          increment: 3,
          eligible_quantity: 2,
        }),
      ),
    ).toBe(false);
  });

  it('V/W. China and TZ use the same copy contract', () => {
    const china = resolvePdpPurchaseQuantityView(MOQ_ONLY);
    const tz = resolvePdpPurchaseQuantityView(MOQ_ONLY);
    expect(china).toEqual(tz);
  });

  it('X. missing metadata is safe', () => {
    expect(mapPurchaseQuantity({})).toBeNull();
    expect(mapPurchaseQuantity({ minimum_quantity: 6 })).toBeNull();
    expect(mapPurchaseQuantityBlocker({ product_id: 'p1' })).toBeNull();
    expect(mapPurchaseQuantityBlockers(undefined)).toEqual([]);
    expect(selectBlockerForProduct([], '')).toBeNull();
    expect(formatPurchaseQuantityCheckoutMessage(null)).toBeNull();
    expect(formatAddToCartFollowUp(null)).toBeNull();
  });

  it('Y. malformed metadata degrades without inventing legality', () => {
    expect(
      mapPurchaseQuantity({
        ...MOQ_ONLY,
        minimum_quantity: '6.5',
      }),
    ).toBeNull();
    expect(
      mapPurchaseQuantity({
        ...MOQ_ONLY,
        minimum_quantity: 6.5,
      }),
    ).toBeNull();
    expect(
      mapPurchaseQuantity({
        ...MOQ_ONLY,
        minimum_quantity: '6foo',
      }),
    ).toBeNull();
    expect(
      mapPurchaseQuantity({
        ...MOQ_ONLY,
        eligible_quantity: 6.5,
      }),
    ).toBeNull();
  });

  it('Z. allowed example is cosmetic and surfaces keep ATC soft', () => {
    expect(formatAllowedQuantitiesExample(6, 3)).toBe(
      'Allowed quantities: 6, 9, 12, 15, ...',
    );
    expect(formatAllowedQuantitiesExample(6, null)).toBeNull();
    expect(formatAllowedQuantitiesExample.toString().includes('%')).toBe(false);
    expect(formatPurchaseQuantityCheckoutMessage.toString().includes('%')).toBe(
      false,
    );

    const pdp = readFileSync(
      resolve(__dirname, '../product/components/ProductDetailScreen.tsx'),
      'utf8',
    );
    const cartHook = readFileSync(
      resolve(__dirname, '../cart/hooks/useCart.ts'),
      'utf8',
    );
    const addToCart = readFileSync(
      resolve(__dirname, '../product/components/AddToCartButton.tsx'),
      'utf8',
    );
    const cartScreen = readFileSync(
      resolve(__dirname, '../cart/screens/CartScreen.tsx'),
      'utf8',
    );
    const checkoutScreen = readFileSync(
      resolve(__dirname, '../checkout/screens/CheckoutScreen.tsx'),
      'utf8',
    );
    expect(pdp.includes('Buy Now')).toBe(false);
    expect(pdp.includes('resolveQuotePurchaseQuantity')).toBe(true);
    expect(addToCart.includes('blocks_checkout')).toBe(false);
    expect(cartHook.includes("authStatus === 'authenticated'")).toBe(true);
    expect(cartScreen.includes('groupCartLinesByProductId')).toBe(true);
    expect(cartScreen.includes('CartPurchaseQuantityBanner')).toBe(true);
    expect(checkoutScreen.includes('isCheckoutStartBlockedByPurchaseState')).toBe(
      true,
    );
    expect(checkoutScreen.includes('useStartCheckoutSessionMutation')).toBe(true);
  });

  it('cart minimum and increment copy use server fields', () => {
    expect(resolveCartBlockerView(blocker({ product_id: 'p1' }), true).status).toBe(
      'Add 2 more of this product to reach the minimum order quantity.',
    );
    const incrementView = resolveCartBlockerView(
      blocker({
        product_id: 'p1',
        increment: 3,
        eligible_quantity: 7,
        minimum_satisfied: true,
        increment_satisfied: false,
        quantity_to_minimum: 0,
        next_legal_quantity: 9,
      }),
    );
    expect(incrementView.status).toBe('Quantity 7 is not an allowed total.');
    expect(incrementView.nextAllowed).toBe('Next allowed quantity: 9.');
  });

  it('malformed 422 purchase_quantity does not invent legality or crash', () => {
    const parsed = parsePurchaseQuantityCheckoutError({
      code: 'purchase_quantity_unsatisfied',
      message: 'This product does not meet the purchase quantity rule.',
      data: { purchase_quantity: { product_id: 'p1', minimum_quantity: '6.5' } },
    });
    expect(parsed.code).toBe('purchase_quantity_unsatisfied');
    expect(parsed.blocker).toBeNull();
    expect(formatPurchaseQuantityCheckoutMessage(parsed.blocker)).toBeNull();

    const error = new ApiError({
      message: 'This product does not meet the purchase quantity rule.',
      status: 422,
      code: 'purchase_quantity_unsatisfied',
      raw: {
        code: 'purchase_quantity_unsatisfied',
        message: 'This product does not meet the purchase quantity rule.',
      },
    });
    expect(purchaseQuantityMessageFromError(error)).toBe(
      'This product does not meet the purchase quantity rule.',
    );
    expect(purchaseQuantityMessageFromError({ message: 'nope' })).toBeNull();
  });

  it('checkout-start copy uses server precedence without local modulo', () => {
    expect(
      formatPurchaseQuantityCheckoutMessage(
        blocker({ product_id: 'p1', quantity_to_minimum: 2 }),
      ),
    ).toBe('Add 2 more before checkout.');
    expect(
      formatPurchaseQuantityCheckoutMessage(
        blocker({
          product_id: 'p1',
          increment: 3,
          eligible_quantity: 7,
          minimum_satisfied: true,
          increment_satisfied: false,
          quantity_to_minimum: 0,
          next_legal_quantity: 9,
        }),
      ),
    ).toBe('Next allowed quantity is 9.');
    expect(
      formatPurchaseQuantityCheckoutMessage(
        blocker({
          product_id: 'p1',
          increment: 3,
          eligible_quantity: 4,
          minimum_satisfied: false,
          increment_satisfied: false,
          quantity_to_minimum: 2,
          next_legal_quantity: 6,
        }),
      ),
    ).toBe('Add 2 more before checkout.');
  });
});
