import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const searchParams = new URLSearchParams();
  const storeId = url.searchParams.get("store_id");
  if (storeId) searchParams.set("store_id", storeId);
  return proxyAdminApiRequest("/inventory", { method: "GET", searchParams });
}
