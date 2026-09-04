import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  ADMIN_ORDERS_DEFAULT_PER_PAGE,
  ADMIN_ORDERS_MAX_PER_PAGE,
  applyAdminOrdersListFilters,
  applyAdminOrdersListPage,
  buildAdminOrdersSearchParams,
  clampAdminOrdersPerPage,
  defaultAdminOrdersListQuery,
  extractLaravelPaginationMeta,
  mapSourceFilterToCommerceChannel,
  mapStatusFilterToLaravelStatus,
  pageCountFromTotal,
  paginateLocalOrders,
} from "@/lib/admin/admin-orders-pagination";

describe("admin orders pagination helpers", () => {
  it("uses a 20-row default and clamps unbounded per_page", () => {
    assert.equal(ADMIN_ORDERS_DEFAULT_PER_PAGE, 20);
    assert.equal(ADMIN_ORDERS_MAX_PER_PAGE, 100);
    assert.equal(clampAdminOrdersPerPage(undefined), 20);
    assert.equal(clampAdminOrdersPerPage(20), 20);
    assert.equal(clampAdminOrdersPerPage(100), 100);
    assert.equal(clampAdminOrdersPerPage(1000), 100);
    assert.equal(clampAdminOrdersPerPage(0), 20);
  });

  it("derives page count from total and per_page", () => {
    assert.equal(pageCountFromTotal(92, 20), 5);
    assert.equal(pageCountFromTotal(20, 20), 1);
    assert.equal(pageCountFromTotal(21, 20), 2);
    assert.equal(pageCountFromTotal(0, 20), 1);
  });

  it("preserves Laravel pagination metadata including from/to", () => {
    const meta = extractLaravelPaginationMeta(
      {
        data: [],
        meta: {
          current_page: 2,
          last_page: 5,
          per_page: 20,
          total: 92,
          from: 21,
          to: 40,
        },
      },
      { page: 1, perPage: 20, itemCount: 0 },
    );

    assert.deepEqual(meta, {
      current_page: 2,
      last_page: 5,
      per_page: 20,
      total: 92,
      from: 21,
      to: 40,
    });
  });

  it("maps UI source filters onto Laravel commerce_channel", () => {
    assert.equal(mapSourceFilterToCommerceChannel("china"), "CHINA_IMPORT");
    assert.equal(mapSourceFilterToCommerceChannel("local"), "TZ_LOCAL");
    assert.equal(mapSourceFilterToCommerceChannel("all"), undefined);
    assert.equal(mapStatusFilterToLaravelStatus("all"), undefined);
    assert.equal(mapStatusFilterToLaravelStatus("paid"), "paid");
  });

  it("builds page 1 and page 2 query strings for the BFF", () => {
    const pageOne = buildAdminOrdersSearchParams(defaultAdminOrdersListQuery());
    assert.equal(pageOne.get("page"), "1");
    assert.equal(pageOne.get("per_page"), "20");

    const pageTwo = buildAdminOrdersSearchParams({
      page: 2,
      perPage: 20,
      status: "paid",
      search: "COTZ-20260823-000016",
      source: "china",
    });
    assert.equal(pageTwo.get("page"), "2");
    assert.equal(pageTwo.get("per_page"), "20");
    assert.equal(pageTwo.get("status"), "paid");
    assert.equal(pageTwo.get("q"), "COTZ-20260823-000016");
    assert.equal(pageTwo.get("commerce_channel"), "CHINA_IMPORT");
  });

  it("resets to page 1 when filters or search change", () => {
    const onPageTwo = applyAdminOrdersListPage(defaultAdminOrdersListQuery(), 2);
    assert.equal(onPageTwo.page, 2);

    const afterStatus = applyAdminOrdersListFilters(onPageTwo, { status: "paid" });
    assert.equal(afterStatus.page, 1);
    assert.equal(afterStatus.status, "paid");

    const afterSearch = applyAdminOrdersListFilters(afterStatus, { search: "Herriet" });
    assert.equal(afterSearch.page, 1);
    assert.equal(afterSearch.search, "Herriet");
  });

  it("keeps an older row reachable through later local pages", () => {
    const rows = Array.from({ length: 25 }, (_, index) => ({ id: `ord-${index + 1}` }));
    const pageOne = paginateLocalOrders(rows, 1, 20);
    const pageTwo = paginateLocalOrders(rows, 2, 20);

    assert.equal(pageOne.meta.total, 25);
    assert.equal(pageOne.items.length, 20);
    assert.equal(
      pageOne.items.some((row) => row.id === "ord-25"),
      false,
    );
    assert.equal(
      pageTwo.items.some((row) => row.id === "ord-25"),
      true,
    );
  });
});
