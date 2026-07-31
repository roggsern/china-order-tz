import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveAccountGreetingPrefix } from "./arrival-signal";

describe("resolveAccountGreetingPrefix", () => {
  it("uses welcome for first registration arrival", () => {
    assert.equal(
      resolveAccountGreetingPrefix({ isLoggedIn: true, isFirstArrival: true }),
      "welcome",
    );
  });

  it("uses welcome back for returning customers", () => {
    assert.equal(
      resolveAccountGreetingPrefix({ isLoggedIn: true, isFirstArrival: false }),
      "welcome_back",
    );
  });
});
