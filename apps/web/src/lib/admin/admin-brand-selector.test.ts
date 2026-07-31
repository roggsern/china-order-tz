import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import { matchesAdminSearchTerms } from "@/lib/admin/admin-search-utils";
import {
  buildCategoryTreeOptions,
  resolveBrandLeafCategoryId,
} from "@/lib/admin/catalog-selector-utils";
import {
  AdminCatalogApiError,
  createAdminBrand,
  fetchAdminBrandsPage,
} from "@/lib/api/admin-catalog";
import type { AdminCategory } from "@/lib/api/admin-catalog";

function category(
  id: string,
  name: string,
  parentId: string | null = null,
): AdminCategory {
  return {
    id,
    name,
    slug: name.toLowerCase().replace(/\s+/g, "-"),
    parentId,
    departmentId: "dept-1",
    origin: "china",
    isActive: true,
  } as AdminCategory;
}

describe("admin brand selector", () => {
  it("fetchAdminBrandsPage forwards search, category filter, and show-all fallback", async () => {
    const originalFetch = globalThis.fetch;
    const capturedUrls: string[] = [];

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL) => {
      capturedUrls.push(String(input));
      return Response.json({
        success: true,
        data: [
          { id: "b1", name: "Zara", slug: "zara", is_active: true },
          { id: "b2", name: "Zara Kids", slug: "zara-kids", is_active: true },
        ],
        meta: { current_page: 1, last_page: 2, total: 22 },
      });
    }) as typeof fetch;

    try {
      const result = await fetchAdminBrandsPage({
        search: "Zara",
        categoryId: "cat-blouse",
        allBrands: false,
        isActive: true,
        page: 1,
        perPage: 20,
      });

      assert.equal(result.items.length, 2);
      assert.equal(result.items[0]?.name, "Zara");
      assert.equal(result.lastPage, 2);

      const url = capturedUrls[0] ?? "";
      assert.match(url, /search=Zara/);
      assert.match(url, /category_id=cat-blouse/);
      assert.match(url, /is_active=1/);
      assert.doesNotMatch(url, /(?:^|[?&])all=1(?:&|$)/);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("fetchAdminBrandsPage includes all=1 when show-all fallback is enabled", async () => {
    const originalFetch = globalThis.fetch;
    let capturedUrl = "";

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL) => {
      capturedUrl = String(input);
      return Response.json({
        success: true,
        data: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
      });
    }) as typeof fetch;

    try {
      await fetchAdminBrandsPage({
        categoryId: "cat-blouse",
        allBrands: true,
      });

      assert.match(capturedUrl, /(?:^|[?&])all=1(?:&|$)/);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("createAdminBrand sends inline create payload with optional category link", async () => {
    const originalFetch = globalThis.fetch;
    let capturedBody: unknown;

    globalThis.fetch = mock.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      capturedBody = init?.body ? JSON.parse(String(init.body)) : null;
      return Response.json({
        success: true,
        data: {
          id: "brand-new",
          name: "New Label",
          slug: "new-label",
          is_active: true,
        },
      });
    }) as typeof fetch;

    try {
      const brand = await createAdminBrand({
        name: "New Label",
        description: "Inline create",
        logo: null,
        is_active: true,
        category_ids: ["cat-blouse"],
      });

      assert.equal(brand.id, "brand-new");
      assert.deepEqual(capturedBody, {
        name: "New Label",
        description: "Inline create",
        logo: null,
        is_active: true,
        category_ids: ["cat-blouse"],
      });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("createAdminBrand surfaces backend authorization errors", async () => {
    const originalFetch = globalThis.fetch;

    globalThis.fetch = mock.fn(async () =>
      Response.json(
        { success: false, message: "This action is unauthorized." },
        { status: 403 },
      ),
    ) as typeof fetch;

    try {
      await assert.rejects(
        () =>
          createAdminBrand({
            name: "Blocked Brand",
            is_active: true,
          }),
        (error: unknown) => {
          assert.ok(error instanceof AdminCatalogApiError);
          assert.equal(error.statusCode, 403);
          return true;
        },
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("category tree search supports parent and child labels", () => {
    const women = category("c1", "Women's Fashion");
    const blouse = category("c2", "Blouse", "c1");
    const options = buildCategoryTreeOptions([women, blouse]);

    const filtered = options.filter((option) =>
      matchesAdminSearchTerms(`${option.label} ${option.description ?? ""}`, "blouse"),
    );

    assert.equal(filtered.length, 1);
    assert.equal(filtered[0]?.id, "c2");
    assert.match(filtered[0]?.label ?? "", /Blouse/);
  });

  it("resolveBrandLeafCategoryId prefers subcategory for category-aware brand filtering", () => {
    assert.equal(resolveBrandLeafCategoryId("c1", "c2"), "c2");
    assert.equal(resolveBrandLeafCategoryId("c1", ""), "c1");
  });
});
