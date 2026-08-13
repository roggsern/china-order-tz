import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { createSharedAsyncCache } from "./shared-async-cache";

describe("createSharedAsyncCache", () => {
  it("dedupes concurrent same-key fetches into one loader start", async () => {
    const cache = createSharedAsyncCache({ ttlMs: 60_000 });
    let starts = 0;

    const loader = async () => {
      starts += 1;
      await new Promise((r) => setTimeout(r, 20));
      return "nav-payload";
    };

    const [a, b, c] = await Promise.all([
      cache.getOrFetch("guest", loader),
      cache.getOrFetch("guest", loader),
      cache.getOrFetch("guest", loader),
    ]);

    assert.equal(a, "nav-payload");
    assert.equal(b, "nav-payload");
    assert.equal(c, "nav-payload");
    assert.equal(starts, 1);
    assert.equal(cache.getStats().starts, 1);
    assert.equal(cache.getStats().joins, 2);
  });

  it("keeps guest and auth keys isolated", async () => {
    const cache = createSharedAsyncCache({ ttlMs: 60_000 });
    const seen: string[] = [];

    await Promise.all([
      cache.getOrFetch("guest", async () => {
        seen.push("guest");
        return { audience: "guest" };
      }),
      cache.getOrFetch("authenticated", async () => {
        seen.push("authenticated");
        return { audience: "authenticated" };
      }),
    ]);

    assert.deepEqual(seen.sort(), ["authenticated", "guest"]);
    assert.equal(cache.getStats().starts, 2);

    const guest = await cache.getOrFetch("guest", async (): Promise<{ audience: string }> => {
      throw new Error("should hit cache");
    });
    assert.equal(guest.audience, "guest");
    assert.equal(cache.getStats().hits, 1);
  });

  it("does not leave a pending entry after failure", async () => {
    const cache = createSharedAsyncCache({ ttlMs: 60_000 });
    let attempts = 0;

    await assert.rejects(
      () =>
        cache.getOrFetch("fail", async () => {
          attempts += 1;
          throw new Error("boom");
        }),
      /boom/,
    );

    const value = await cache.getOrFetch("fail", async () => {
      attempts += 1;
      return "recovered";
    });

    assert.equal(value, "recovered");
    assert.equal(attempts, 2);
  });
});
