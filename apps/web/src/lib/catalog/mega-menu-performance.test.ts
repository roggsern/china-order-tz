import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it, beforeEach } from "node:test";
import path from "node:path";
import {
  __clearChinaStorefrontMenuCacheForTests,
  __getChinaStorefrontMenuCacheStatsForTests,
} from "./use-china-storefront-menu";
import { createSharedAsyncCache } from "@/lib/storefront/shared-async-cache";

const megaMenuPath = path.resolve(
  process.cwd(),
  "src/components/home/MegaMenu.tsx",
);
const tzMegaPath = path.resolve(
  process.cwd(),
  "src/components/home/BuyFromTzMegaMenu.tsx",
);
const navHookPath = path.resolve(
  process.cwd(),
  "src/lib/storefront/use-storefront-navigation.ts",
);

describe("mega menu performance hotfix contracts", () => {
  beforeEach(() => {
    __clearChinaStorefrontMenuCacheForTests();
  });

  it("China mega trigger remains interactive while loading (no busy-only early return)", () => {
    const source = readFileSync(megaMenuPath, "utf8");
    assert.match(source, /data-testid="china-mega-trigger"/);
    assert.match(source, /data-testid="china-mega-loading"/);
    assert.match(source, /data-testid="china-mega-error"/);
    assert.match(source, /enabled:\s*fetchEnabled/);
    assert.doesNotMatch(
      source,
      /if \(isLoading && !menu\) \{\s*return \(\s*<div className="group relative">\s*<button[^>]*aria-busy="true"/,
    );
  });

  it("TZ mega trigger remains interactive while loading", () => {
    const source = readFileSync(tzMegaPath, "utf8");
    assert.match(source, /data-testid="tz-mega-trigger"/);
    assert.match(source, /data-testid="tz-mega-loading"/);
    assert.match(source, /data-testid="tz-mega-error"/);
    assert.doesNotMatch(source, /if \(isLoading\) \{\s*return \(\s*<div className="group relative">/);
  });

  it("navigation fetch disables CMS mega hydration to avoid duplicate China work", () => {
    const source = readFileSync(navHookPath, "utf8");
    assert.match(source, /hydrateMegaMenus:\s*false/);
    assert.doesNotMatch(source, /hydrateMegaMenus:\s*true/);
  });

  it("dedupes concurrent china menu fetches for the same key", async () => {
    const cache = createSharedAsyncCache({ ttlMs: 60_000 });
    let starts = 0;
    const loader = async () => {
      starts += 1;
      await new Promise((r) => setTimeout(r, 15));
      return { categories: [] };
    };

    await Promise.all([
      cache.getOrFetch("china-menu:__root__", loader),
      cache.getOrFetch("china-menu:__root__", loader),
      cache.getOrFetch("china-menu:__root__", loader),
    ]);

    assert.equal(starts, 1);
    assert.deepEqual(__getChinaStorefrontMenuCacheStatsForTests(), {
      starts: 0,
      joins: 0,
      hits: 0,
    });
  });

  it("keeps distinct keys for guest vs authenticated navigation", async () => {
    const cache = createSharedAsyncCache({ ttlMs: 60_000 });
    const keys: string[] = [];

    await Promise.all([
      cache.getOrFetch("nav:GLOBAL:guest:hydrate=0", async () => {
        keys.push("guest");
        return { audience: "guest" };
      }),
      cache.getOrFetch("nav:GLOBAL:authenticated:hydrate=0", async () => {
        keys.push("authenticated");
        return { audience: "authenticated" };
      }),
      cache.getOrFetch("nav:GLOBAL:guest:hydrate=0", async () => {
        keys.push("guest-again");
        return { audience: "guest" };
      }),
    ]);

    assert.deepEqual(keys.sort(), ["authenticated", "guest"]);
    assert.equal(cache.getStats().starts, 2);
    assert.equal(cache.getStats().joins, 1);
  });
});
