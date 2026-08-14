import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it } from "node:test";
import path from "node:path";

const megaMenuSource = readFileSync(
  path.join(process.cwd(), "src/components/home/MegaMenu.tsx"),
  "utf8",
);

describe("China mega-menu featured compact strip", () => {
  it("renders one compact 4-column featured row (not multi-row ProductCard grid)", () => {
    assert.match(megaMenuSource, /data-testid="china-mega-featured-strip"/);
    assert.match(megaMenuSource, /grid grid-cols-4 gap-2 overflow-hidden/);
    assert.match(megaMenuSource, /featured\.slice\(0,\s*4\)/);
    assert.doesNotMatch(megaMenuSource, /featured\.slice\(0,\s*6\)/);
    assert.doesNotMatch(megaMenuSource, /grid-cols-2 gap-3 sm:grid-cols-3/);
    assert.doesNotMatch(megaMenuSource, /<ProductCard/);
  });

  it("keeps featured tiles as PDP links without cart controls", () => {
    assert.match(megaMenuSource, /href=\{`\/products\/\$\{product\.slug\}`\}/);
    assert.doesNotMatch(megaMenuSource, /Add to [Cc]art/);
    assert.doesNotMatch(megaMenuSource, /Select [Oo]ptions/);
  });
});
