import assert from "node:assert/strict";
import { test } from "node:test";
import { buildMoqProbeQuantities } from "./quote";

test("MOQ discovery can inspect quantity 100 when stock is at least 100", () => {
  const probes = buildMoqProbeQuantities(250, 1);
  assert.ok(probes.includes(100), `expected 100 in ${probes.join(",")}`);
  assert.ok(probes.includes(250), `expected stock ceiling 250 in ${probes.join(",")}`);
  assert.equal(probes.includes(251), false);
});

test("MOQ discovery does not probe beyond available stock", () => {
  const probes = buildMoqProbeQuantities(5, 1);
  assert.deepEqual(probes, [1, 2, 3, 4, 5]);
});

test("MOQ discovery still probes 1..20 and common increments when stock allows", () => {
  const probes = buildMoqProbeQuantities(50, 7);
  for (const qty of [1, 7, 10, 20, 25, 30, 40, 50]) {
    assert.ok(probes.includes(qty), `expected increment/MOQ probe ${qty}`);
  }
  assert.equal(probes.includes(100), false);
});

test("MOQ discovery stays bounded for large stock", () => {
  const probes = buildMoqProbeQuantities(10_000, 1);
  assert.ok(probes.includes(100));
  assert.ok(probes.includes(10_000));
  assert.ok(probes.length <= 32);
});

test("MOQ discovery is fail-closed when stock is unknown or zero", () => {
  assert.deepEqual(buildMoqProbeQuantities(0, 1), []);
  assert.deepEqual(buildMoqProbeQuantities(Number.NaN, 1), []);
});
