import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  CustomerReviewApiError,
  isReviewFeatureDisabledError,
  mapServerProductReview,
} from "./customer-reviews";

describe("customer reviews API helpers", () => {
  it("maps server review payloads for display", () => {
    const mapped = mapServerProductReview({
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      rating: 4,
      title: "Solid",
      comment: "Works well.",
      author: "Jane Doe",
      verified: true,
      created_at: "2026-01-10T08:00:00.000Z",
    });

    assert.equal(mapped.rating, 4);
    assert.equal(mapped.title, "Solid");
    assert.equal(mapped.comment, "Works well.");
    assert.equal(mapped.author, "Jane Doe");
    assert.equal(mapped.verified, true);
    assert.ok(mapped.date.length > 0);
    assert.ok(typeof mapped.id === "number");
  });

  it("detects disabled review feature responses", () => {
    assert.equal(
      isReviewFeatureDisabledError(new CustomerReviewApiError("Forbidden", 403)),
      true,
    );
    assert.equal(
      isReviewFeatureDisabledError(new CustomerReviewApiError("Unauthorized", 401)),
      false,
    );
  });
});
