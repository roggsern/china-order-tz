import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it } from "node:test";
import path from "node:path";
import { serverCartMatchesCheckoutLines } from "@/lib/api/customer-checkout";

const checkoutPath = path.resolve(process.cwd(), "src/lib/api/customer-checkout.ts");
const clientCatalogPath = path.resolve(process.cwd(), "src/lib/catalog/client-catalog.ts");
const productsPath = path.resolve(process.cwd(), "src/lib/api/products.ts");
const paymentPagePath = path.resolve(
  process.cwd(),
  "src/components/checkout/PaymentPageContent.tsx",
);
const checkoutPagePath = path.resolve(
  process.cwd(),
  "src/components/checkout/CheckoutPageContent.tsx",
);

describe("Wave 5 checkout → payment latency contracts", () => {
  it("validates cart via checkout-summary not PDP show", () => {
    const catalog = readFileSync(clientCatalogPath, "utf8");
    const products = readFileSync(productsPath, "utf8");
    assert.match(catalog, /getProductCheckoutSummary/);
    assert.doesNotMatch(catalog, /\bgetProduct\(/);
    assert.match(products, /buildCatalogProductCheckoutSummaryBffPath/);
    assert.match(products, /getProductCheckoutSummary/);
  });

  it("skips destructive cart sync when server cart already matches", () => {
    const source = readFileSync(checkoutPath, "utf8");
    assert.match(source, /serverCartMatchesCheckoutLines/);
    assert.match(source, /fetchServerCart/);
    assert.match(source, /await clearServerCart\(token\)/);

    assert.equal(
      serverCartMatchesCheckoutLines(
        {
          id: "cart-1",
          items: [
            {
              id: "line-1",
              product_id: "prod-1",
              product_variant_id: "var-1",
              quantity: 2,
              unit_price: 1000,
              shipping_method: "air",
            },
          ],
        },
        [
          {
            productId: "prod-1",
            variantId: "var-1",
            quantity: 2,
            shippingMethod: "air",
          },
        ],
      ),
      true,
    );

    assert.equal(
      serverCartMatchesCheckoutLines(
        {
          id: "cart-1",
          items: [
            {
              id: "line-1",
              product_id: "prod-1",
              product_variant_id: null,
              quantity: 1,
              unit_price: 1000,
              shipping_method: null,
            },
          ],
        },
        [
          {
            productId: "prod-1",
            variantId: null,
            quantity: 2,
            shippingMethod: null,
          },
        ],
      ),
      false,
    );
  });

  it("loads payment methods on payment mount without waiting for draft ready", () => {
    const page = readFileSync(paymentPagePath, "utf8");
    assert.match(page, /prefetchCheckoutPaymentMethods\(/);
    assert.match(page, /fetchCheckoutPaymentMethods\(/);
    assert.doesNotMatch(page, /}, \[isReady\]\);/);
  });

  it("keeps checkout continue double-submit guard and summary validation", () => {
    const page = readFileSync(checkoutPagePath, "utf8");
    assert.match(page, /fetchClientCatalogProductsForSlugs/);
    assert.match(page, /submitInFlightRef\.current \|\| isSubmitting/);
  });
});
