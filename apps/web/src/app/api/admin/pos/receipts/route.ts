import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const qs = url.searchParams.toString();
  return proxyAdminApiRequest(`/pos/receipts${qs ? `?${qs}` : ""}`, { method: "GET" });
}
