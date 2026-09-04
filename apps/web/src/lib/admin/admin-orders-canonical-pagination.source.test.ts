import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";

const webSrc = join(process.cwd(), "src");

function readWeb(relativePath: string): string {
  return readFileSync(join(webSrc, relativePath), "utf8");
}

describe("admin orders canonical pagination source contracts", () => {
  it("detail fetches the order by ID instead of searching the list snapshot", () => {
    const detail = readWeb("components/admin/AdminOrderDetailContent.tsx");
    const showRoute = readWeb("app/api/admin/orders/[order]/route.ts");

    assert.ok(detail.includes("fetchAdminOrderById"));
    assert.ok(detail.includes('useState<DetailLoadState>("loading")'));
    assert.ok(detail.includes("error.statusCode === 404"));
    assert.ok(detail.includes("Order not found"));
    assert.ok(detail.includes("Unable to load order"));
    assert.ok(detail.includes("void loadOrder()"));
    assert.doesNotMatch(detail, /if\s*\(\s*!order\s*\)/);
    assert.doesNotMatch(detail, /getOrderById\(orderId\)\s*;\s*\n\s*if\s*\(/);

    assert.ok(showRoute.includes("proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}`"));
    assert.ok(showRoute.includes("mapLaravelAdminOrderPayloadToWebOrder"));
    assert.doesNotMatch(showRoute, /fetchAdminOrdersPage/);
    assert.doesNotMatch(showRoute, /paginate\(1000\)/);
  });

  it("does not flash not-found before the direct show request completes", () => {
    const detail = readWeb("components/admin/AdminOrderDetailContent.tsx");
    const loadingIndex = detail.indexOf('loadState === "loading" && !displayOrder');
    const notFoundIndex = detail.indexOf('loadState === "not_found"');

    assert.ok(loadingIndex > 0);
    assert.ok(notFoundIndex > loadingIndex);
  });

  it("keeps Fulfillment View Order on the canonical order id", () => {
    const fulfillment = readWeb("components/admin/AdminFulfillmentOperationalWorkspace.tsx");
    assert.ok(fulfillment.includes("`/admin/orders/${encodeURIComponent(model.order.id)}`"));
    assert.ok(fulfillment.includes("View order"));
  });

  it("forwards page, per_page, filters, and Laravel pagination metadata through the BFF", () => {
    const indexRoute = readWeb("app/api/admin/orders/route.ts");
    const provider = readWeb("components/admin/AdminOrdersProvider.tsx");
    const table = readWeb("components/admin/AdminOrderTable.tsx");

    assert.ok(indexRoute.includes('upstream.set("page", page)'));
    assert.ok(indexRoute.includes('upstream.set("per_page", perPage)'));
    assert.ok(indexRoute.includes("extractLaravelPaginationMeta"));
    assert.ok(indexRoute.includes("mapSourceFilterToCommerceChannel"));
    assert.doesNotMatch(indexRoute, /filterAdminOrders\(mapped/);
    assert.doesNotMatch(indexRoute, /filterAdminOrders\(withSummary/);

    assert.ok(provider.includes("listMeta"));
    assert.ok(provider.includes("setListPage"));
    assert.ok(provider.includes("applyAdminOrdersListFilters"));
    assert.ok(provider.includes("shouldApplyAdminOrdersListResponse"));
    assert.ok(provider.includes("fetchAdminOrdersSnapshot(requestedQuery)"));
    assert.doesNotMatch(provider, /fetchAdminProducts/);
    assert.doesNotMatch(provider, /fetchAllPaginated/);

    assert.ok(table.includes("setListPage"));
    assert.ok(table.includes("listMeta.total"));
    assert.ok(table.includes("Loading page ${requestedPage}"));
    assert.ok(table.includes("pageOrders.map"));
    assert.doesNotMatch(table, /PAGE_SIZE/);
    assert.doesNotMatch(table, /disabled=\{isListLoading\}/);
    assert.doesNotMatch(table, /fetchAdminProducts/);
  });

  it("uses server total for the Orders index header rather than the current page slice", () => {
    const page = readWeb("app/admin/orders/page.tsx");
    assert.ok(page.includes("listMeta.total"));
    assert.doesNotMatch(page, /analytics\.totalOrders/);
  });

  it("does not refetch the admin catalog when orders pagination state changes", () => {
    const layout = readWeb("app/admin/layout.tsx");
    const productsProvider = readWeb("components/admin/AdminProductsProvider.tsx");
    const ordersProvider = readWeb("components/admin/AdminOrdersProvider.tsx");
    const table = readWeb("components/admin/AdminOrderTable.tsx");
    const action = readFileSync(
      join(process.cwd(), "..", "api", "app", "Actions", "AdminOrders", "GetAdminOrdersAction.php"),
      "utf8",
    );

    assert.ok(layout.includes("AdminProductsProvider"));
    assert.ok(productsProvider.includes("[isAuthenticated, isReady, loadProducts]"));
    assert.doesNotMatch(productsProvider, /listQuery/);
    assert.doesNotMatch(ordersProvider, /fetchAdminProducts/);
    assert.doesNotMatch(table, /fetchAdminProducts/);
    assert.doesNotMatch(action, /items\.product/);
    assert.ok(action.includes("'items'"));
  });
});
