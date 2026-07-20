import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of [
    "search",
    "lifecycle_status",
    "registration_source",
    "tag",
    "tag_id",
    "registered_from",
    "registered_to",
    "last_order_from",
    "last_order_to",
    "min_spend",
    "max_spend",
    "min_orders",
    "max_orders",
    "no_orders",
    "dormant",
    "blocked",
    "sort",
    "direction",
    "page",
    "per_page",
  ]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }

  return proxyAdminApiRequest("/customers", { method: "GET", searchParams });
}
