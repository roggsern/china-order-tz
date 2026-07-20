import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  for (const key of ["store_id", "search", "page", "per_page"]) {
    const value = url.searchParams.get(key);
    if (value) searchParams.set(key, value);
  }
  return proxyAdminApiRequest("/inventory/stock", { method: "GET", searchParams });
}
