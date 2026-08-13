import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it } from "node:test";
import path from "node:path";

const checkoutPath = path.resolve(
  process.cwd(),
  "src/lib/api/customer-checkout.ts",
);
const checkoutPagePath = path.resolve(
  process.cwd(),
  "src/components/checkout/CheckoutPageContent.tsx",
);
const clientCatalogPath = path.resolve(
  process.cwd(),
  "src/lib/catalog/client-catalog.ts",
);
const paymentMethodsPath = path.resolve(
  process.cwd(),
  "src/lib/api/checkout-payment-methods.ts",
);

describe("Wave 4 checkout → payment latency contracts", () => {
  it("syncs cart lines in parallel after clear", () => {
    const source = readFileSync(checkoutPath, "utf8");
    assert.match(source, /await clearServerCart\(token\)/);
    assert.match(source, /await Promise\.all\(\s*resolved\.map/);
    assert.match(source, /await Promise\.all\(\s*items\.map/);
  });

  it("validates checkout cart via slug-scoped catalog fetch", () => {
    const page = readFileSync(checkoutPagePath, "utf8");
    const catalog = readFileSync(clientCatalogPath, "utf8");
    assert.match(page, /fetchClientCatalogProductsForSlugs/);
    assert.match(catalog, /export async function fetchClientCatalogProductsForSlugs/);
    assert.match(catalog, /Promise\.all\(/);
  });

  it("prefetches payment methods while on checkout", () => {
    const page = readFileSync(checkoutPagePath, "utf8");
    const methods = readFileSync(paymentMethodsPath, "utf8");
    assert.match(page, /prefetchCheckoutPaymentMethods\(/);
    assert.match(methods, /export function prefetchCheckoutPaymentMethods/);
    assert.match(methods, /PREFETCH_TTL_MS/);
  });

  it("keeps continue-to-payment double-submit guard", () => {
    const page = readFileSync(checkoutPagePath, "utf8");
    assert.match(page, /submitInFlightRef\.current \|\| isSubmitting/);
    assert.match(page, /setIsSubmitting\(true\)/);
  });
});
