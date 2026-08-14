import assert from "node:assert/strict";
import { test } from "node:test";
import {
  DEFAULT_ADD_TO_CART_FAILURE_MESSAGE,
  runAddToCartUi,
  runBuyNowUi,
  type AddToCartResult,
} from "./add-to-cart-ui";

test("Add to Cart UI awaits success before toast/drawer handlers", async () => {
  const order: string[] = [];
  let resolveAdd!: (result: AddToCartResult) => void;

  const pending = new Promise<AddToCartResult>((resolve) => {
    resolveAdd = resolve;
  });

  const ui = runAddToCartUi(() => {
    order.push("add-started");
    return pending;
  }, {
    onSuccess: () => {
      order.push("success-ui");
    },
    onFailure: () => {
      order.push("failure-ui");
    },
  });

  order.push("after-click");
  resolveAdd({ ok: true });
  const result = await ui;

  assert.equal(result.ok, true);
  assert.deepEqual(order, ["add-started", "after-click", "success-ui"]);
});

test("Add to Cart UI does not show success on failure", async () => {
  const events: string[] = [];
  const result = await runAddToCartUi(
    async () => ({ ok: false, message: "Unable to add item to your cart." }),
    {
      onSuccess: () => {
        events.push("success");
      },
      onFailure: (message) => {
        events.push(`failure:${message}`);
      },
    },
  );

  assert.equal(result.ok, false);
  assert.deepEqual(events, [`failure:${DEFAULT_ADD_TO_CART_FAILURE_MESSAGE}`]);
});

test("Buy Now navigates only after successful add", async () => {
  const events: string[] = [];
  const result = await runBuyNowUi(async () => ({ ok: true, recoveredFromStaleAuth: true }), {
    onSuccess: () => {
      events.push("navigate-checkout");
    },
    onFailure: () => {
      events.push("failure");
    },
  });

  assert.equal(result.ok, true);
  assert.deepEqual(events, ["navigate-checkout"]);
});

test("Buy Now does not navigate on unrecoverable failure", async () => {
  const events: string[] = [];
  const result = await runBuyNowUi(async () => ({ ok: false, message: "Stock limit reached." }), {
    onSuccess: () => {
      events.push("navigate-checkout");
    },
    onFailure: (message) => {
      events.push(`failure:${message}`);
    },
  });

  assert.equal(result.ok, false);
  assert.deepEqual(events, ["failure:Stock limit reached."]);
});
