import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildProductStockUpdatePayload,
  emptyProductStockFormState,
  mapProductStockToFormState,
  validateProductStockFormState,
} from "./product-stock-sync";
import { mapAdminApiProductStock } from "@/lib/api/admin-catalog";

test("mapAdminApiProductStock maps simple inventory row", () => {
  const stock = mapAdminApiProductStock([
    {
      id: "019f7a6e-1111-7376-aca4-aed79f33519b",
      quantity: 12,
      reserved_quantity: 2,
      available_quantity: 10,
    },
  ]);

  assert.equal(stock.id, "019f7a6e-1111-7376-aca4-aed79f33519b");
  assert.equal(stock.quantity, 12);
  assert.equal(stock.reservedQuantity, 2);
  assert.equal(stock.availableQuantity, 10);
  assert.equal(stock.hasPolicy, true);
});

test("mapAdminApiProductStock returns empty policy when inventory missing", () => {
  const stock = mapAdminApiProductStock([]);

  assert.equal(stock.hasPolicy, false);
  assert.equal(stock.quantity, 0);
  assert.equal(stock.availableQuantity, 0);
});

test("mapProductStockToFormState loads on-hand quantity", () => {
  const form = mapProductStockToFormState({
    id: "inv-1",
    quantity: 7,
    reservedQuantity: 1,
    availableQuantity: 6,
    hasPolicy: true,
  });

  assert.deepEqual(form, { quantity: 7 });
});

test("buildProductStockUpdatePayload mirrors legacy stock_quantity field", () => {
  assert.deepEqual(buildProductStockUpdatePayload({ quantity: 15 }), {
    stock_quantity: 15,
  });
});

test("validateProductStockFormState rejects negative and fractional values", () => {
  assert.match(
    validateProductStockFormState({ quantity: -1 }).quantity ?? "",
    /zero or greater/i,
  );
  assert.match(
    validateProductStockFormState({ quantity: 1.5 }).quantity ?? "",
    /whole number/i,
  );
  assert.deepEqual(validateProductStockFormState(emptyProductStockFormState()), {});
});
