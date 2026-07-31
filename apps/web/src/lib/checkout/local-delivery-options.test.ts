import assert from "node:assert/strict";
import { test } from "node:test";
import {
  TZ_LOCAL_DELIVERY_OPTIONS,
  tzLocalDeliveryOptionsShowPrice,
} from "./local-delivery-options";

test("TZ local delivery options do not expose a price display", () => {
  assert.equal(tzLocalDeliveryOptionsShowPrice(), false);
});

test("self pickup option uses name and explanation only", () => {
  const option = TZ_LOCAL_DELIVERY_OPTIONS.find((entry) => entry.value === "self_pickup");

  assert.ok(option);
  assert.equal(option.title, "Self Pickup");
  assert.match(option.description, /collect your order/i);
  assert.doesNotMatch(option.description, /TZS|0\.00/i);
});

test("delivery option explains fee is confirmed separately", () => {
  const option = TZ_LOCAL_DELIVERY_OPTIONS.find(
    (entry) => entry.value === "negotiated_delivery",
  );

  assert.ok(option);
  assert.equal(option.title, "Delivery");
  assert.match(option.description, /confirmed separately/i);
});

test("shipping_choice values remain unchanged", () => {
  assert.deepEqual(
    TZ_LOCAL_DELIVERY_OPTIONS.map((option) => option.value),
    ["self_pickup", "negotiated_delivery"],
  );
});
