import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";
import {
  buildFooterBuyFromTzLinks,
  defaultFooterBuyFromTzLinks,
  FOOTER_BRAND_CREDIT,
  FOOTER_CONTACT_LINKS,
  normalizeFooterColumn,
  sortFooterTzStores,
} from "./footer-content";

describe("footer-content", () => {
  it("uses updated support contact details", () => {
    assert.ok(
      FOOTER_CONTACT_LINKS.some((link) => link.label === "support@chinaordertz.com"),
    );
    assert.ok(FOOTER_CONTACT_LINKS.some((link) => link.label === "+255 724 557 711"));
    assert.ok(
      FOOTER_CONTACT_LINKS.some((link) => link.label === "Dar es Salaam, Tanzania"),
    );
  });

  it("replaces Order from China with How It Works in About links", () => {
    const about = normalizeFooterColumn({
      key: "about",
      title: "About",
      links: [{ label: "Order from China", href: "/#order-from-china" }],
    });

    assert.deepEqual(
      about.links.map((link) => link.label),
      ["Our Story", "Why Choose Us", "How It Works"],
    );
    assert.ok(about.links.some((link) => link.href === "/#how-it-works"));
  });

  it("lists TZ stores without an All stores link", () => {
    const links = defaultFooterBuyFromTzLinks();

    assert.equal(links.length, 4);
    assert.ok(!links.some((link) => link.label.toLowerCase() === "all stores"));
    assert.deepEqual(
      links.map((link) => link.label),
      ["ROVI BEAUTY", "ZION MODE", "TZUR JEWELRY", "PEACHY LINGERIE"],
    );
    assert.equal(links[0]?.href, "/buy-from-tz/rovi-beauty");
  });

  it("sorts live store links in the canonical footer order", () => {
    const links = buildFooterBuyFromTzLinks([
      { name: "PEACHY LINGERIE", slug: "peachy-lingerie" },
      { name: "ROVI BEAUTY", slug: "rovi-beauty" },
      { name: "TZUR JEWELRY", slug: "tzur-jewelry" },
      { name: "ZION MODE", slug: "zion-mode" },
    ]);

    assert.deepEqual(
      links.map((link) => link.href),
      [
        "/buy-from-tz/rovi-beauty",
        "/buy-from-tz/zion-mode",
        "/buy-from-tz/tzur-jewelry",
        "/buy-from-tz/peachy-lingerie",
      ],
    );
  });

  it("sorts stores by canonical slug order", () => {
    const sorted = sortFooterTzStores([
      { slug: "peachy-lingerie", name: "PEACHY LINGERIE" },
      { slug: "rovi-beauty", name: "ROVI BEAUTY" },
    ]);

    assert.deepEqual(sorted.map((store) => store.slug), ["rovi-beauty", "peachy-lingerie"]);
  });

  it("includes Roggy Tech brand credit copy", () => {
    assert.equal(FOOTER_BRAND_CREDIT.line1, "Created and Designed by ROGGY TECH");
    assert.equal(FOOTER_BRAND_CREDIT.phone, "+255 743 964 569");
  });
});

describe("Footer component integration", () => {
  it("removes newsletter UI and placeholder social links", () => {
    const source = readFileSync(
      join(process.cwd(), "src/components/home/Footer.tsx"),
      "utf8",
    );

    assert.doesNotMatch(source, /FooterNewsletter/);
    assert.doesNotMatch(source, /socialLinks/);
    assert.match(source, /FOOTER_BRAND_CREDIT/);
  });

  it("removes homepage newsletter banner before footer", () => {
    const source = readFileSync(
      join(process.cwd(), "src/app/(storefront)/page.tsx"),
      "utf8",
    );

    assert.doesNotMatch(source, /CommercialNewsletter/);
  });
});
