import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildFulfillmentQueueServerFilters,
  clampPage,
  mapJourneyFilterToStrategy,
  mapStatusFilterToApi,
} from "@/lib/admin/fulfillment-queue-pagination";

describe("fulfillment queue pagination helpers", () => {
  it("maps journey filters to API strategy params", () => {
    assert.equal(mapJourneyFilterToStrategy("all"), undefined);
    assert.equal(mapJourneyFilterToStrategy("china"), "china");
    assert.equal(mapJourneyFilterToStrategy("local"), "local");
  });

  it("maps status filters to API params", () => {
    assert.equal(mapStatusFilterToApi("all"), undefined);
    assert.equal(mapStatusFilterToApi("processing"), "processing");
  });

  it("builds combined server filters", () => {
    assert.deepEqual(
      buildFulfillmentQueueServerFilters({ journey: "local", status: "processing" }),
      { strategy: "local", status: "processing" },
    );
  });

  it("clamps page numbers to valid range", () => {
    assert.equal(clampPage(0, 5), 1);
    assert.equal(clampPage(3, 5), 3);
    assert.equal(clampPage(9, 5), 5);
  });
});
