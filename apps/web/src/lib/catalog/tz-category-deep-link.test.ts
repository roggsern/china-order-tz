import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { ApiCatalogCategory } from "@/lib/api/products";

/** Mirrors Buy From TZ category page ancestor → breadcrumb mapping. */
function ancestorBreadcrumbs(
  storeSlug: string,
  category: Pick<ApiCatalogCategory, "name" | "ancestors">,
): Array<{ label: string; href?: string }> {
  const ancestorCrumbs = (category.ancestors ?? []).map((ancestor) => ({
    label: ancestor.name,
    href: `/buy-from-tz/${storeSlug}/category/${ancestor.slug}`,
  }));
  return [
    { label: "Buy From TZ", href: "/buy-from-tz" },
    { label: "ZION MODE", href: `/buy-from-tz/${storeSlug}` },
    ...ancestorCrumbs,
    { label: category.name },
  ];
}

describe("TZ category deep-link breadcrumbs", () => {
  it("root category has no ancestor crumbs", () => {
    const crumbs = ancestorBreadcrumbs("zion-mode", {
      name: "Pants",
      ancestors: [],
    });
    assert.deepEqual(
      crumbs.map((c) => c.label),
      ["Buy From TZ", "ZION MODE", "Pants"],
    );
  });

  it("child category includes parent crumb with deep-link href", () => {
    const crumbs = ancestorBreadcrumbs("zion-mode", {
      name: "Palazzo Pants",
      ancestors: [
        {
          id: "1",
          name: "Pants",
          slug: "zion-mode-womens-fashion-pants",
        },
      ],
    });
    assert.deepEqual(crumbs, [
      { label: "Buy From TZ", href: "/buy-from-tz" },
      { label: "ZION MODE", href: "/buy-from-tz/zion-mode" },
      {
        label: "Pants",
        href: "/buy-from-tz/zion-mode/category/zion-mode-womens-fashion-pants",
      },
      { label: "Palazzo Pants" },
    ]);
  });

  it("grandchild includes root and parent crumbs", () => {
    const crumbs = ancestorBreadcrumbs("zion-mode", {
      name: "Wide Leg",
      ancestors: [
        { id: "1", name: "Pants", slug: "zion-mode-womens-fashion-pants" },
        {
          id: "2",
          name: "Palazzo Pants",
          slug: "zion-mode-womens-fashion-pants-palazzo-pants",
        },
      ],
    });
    assert.equal(crumbs.at(-1)?.label, "Wide Leg");
    assert.equal(crumbs[2]?.href, "/buy-from-tz/zion-mode/category/zion-mode-womens-fashion-pants");
    assert.equal(
      crumbs[3]?.href,
      "/buy-from-tz/zion-mode/category/zion-mode-womens-fashion-pants-palazzo-pants",
    );
  });
});
