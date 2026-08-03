import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";
import { addPosCartLine } from "@/lib/admin/pos/pos-cart-lines";
import type { PosCartLine, PosCatalogItem } from "@/lib/api/admin-pos";

function catalogItem(overrides: Partial<PosCatalogItem> & Pick<PosCatalogItem, "product_id" | "product_name">): PosCatalogItem {
  return {
    product_sku: null,
    product_variant_id: null,
    variant_name: null,
    variant_sku: null,
    barcode: null,
    unit_price: "10000.00",
    currency: "TZS",
    available_stock: 10,
    in_stock: true,
    ...overrides,
  };
}

describe("addPosCartLine", () => {
  const productA = catalogItem({
    product_id: "prod-wig",
    product_name: "Wig",
    product_sku: "WIG-001",
    is_simple: true,
  });

  const productB = catalogItem({
    product_id: "prod-lotion",
    product_name: "Lotion",
    product_sku: "LOT-001",
    unit_price: "15000.00",
    is_simple: true,
  });

  const variantProduct = catalogItem({
    product_id: "prod-dress",
    product_name: "Dress",
    product_variant_id: "var-red-m",
    variant_name: "Red / M",
    variant_sku: "DRS-RED-M",
    is_simple: false,
  });

  it("adds product A and keeps cart length at 1", () => {
    const cart = addPosCartLine([], productA);

    assert.equal(cart.length, 1);
    assert.equal(cart[0]?.product_id, "prod-wig");
    assert.equal(cart[0]?.quantity, 1);
    assert.equal(cart[0]?.line_total, "10000.00");
  });

  it("adds product B without removing product A", () => {
    const cartAfterA = addPosCartLine([], productA);
    const cart = addPosCartLine(cartAfterA, productB);

    assert.equal(cart.length, 2);
    assert.equal(cart[0]?.product_id, "prod-wig");
    assert.equal(cart[1]?.product_id, "prod-lotion");
  });

  it("increments product A quantity without adding a third line", () => {
    const cartAfterB = addPosCartLine(addPosCartLine([], productA), productB);
    const cart = addPosCartLine(cartAfterB, productA);

    assert.equal(cart.length, 2);
    assert.equal(cart[0]?.product_id, "prod-wig");
    assert.equal(cart[0]?.quantity, 2);
    assert.equal(cart[0]?.line_total, "20000.00");
    assert.equal(cart[1]?.product_id, "prod-lotion");
    assert.equal(cart[1]?.quantity, 1);
  });

  it("keeps simple and variant products in the same cart", () => {
    const cart = addPosCartLine(addPosCartLine([], productA), variantProduct);

    assert.equal(cart.length, 2);
    assert.equal(cart[0]?.product_id, "prod-wig");
    assert.equal(cart[0]?.product_variant_id, null);
    assert.equal(cart[1]?.product_id, "prod-dress");
    assert.equal(cart[1]?.product_variant_id, "var-red-m");
  });

  it("treats different variants of the same product as separate lines", () => {
    const variantBlue = catalogItem({
      product_id: "prod-dress",
      product_name: "Dress",
      product_variant_id: "var-blue-l",
      variant_name: "Blue / L",
      variant_sku: "DRS-BLUE-L",
      is_simple: false,
    });

    const cart = addPosCartLine(addPosCartLine([], variantProduct), variantBlue);

    assert.equal(cart.length, 2);
    assert.equal(cart[0]?.product_variant_id, "var-red-m");
    assert.equal(cart[1]?.product_variant_id, "var-blue-l");
  });

  it("caps quantity at available stock when incrementing an existing line", () => {
    const limited = catalogItem({
      product_id: "prod-wig",
      product_name: "Wig",
      available_stock: 2,
    });

    const cartOnce = addPosCartLine([], limited);
    const cartTwice = addPosCartLine(cartOnce, limited);
    const cartThrice = addPosCartLine(cartTwice, limited);

    assert.equal(cartThrice.length, 1);
    assert.equal(cartThrice[0]?.quantity, 2);
    assert.equal(cartThrice[0]?.line_total, "20000.00");
  });
});

describe("PosCashierPanel cart integration", () => {
  it("uses addPosCartLine for addToCart", () => {
    const source = readFileSync(
      join(process.cwd(), "src/components/admin/pos/PosCashierPanel.tsx"),
      "utf8",
    );

    assert.match(source, /addPosCartLine\(prev, item\)/);
    assert.doesNotMatch(source, /return \[\{ \.\.\.item, quantity: 1/);
  });
});
