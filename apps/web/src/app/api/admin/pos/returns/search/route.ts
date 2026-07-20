import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const qs = new URL(request.url).searchParams.toString();
  return proxyAdminApiRequest(`/pos/returns/search${qs ? `?${qs}` : ""}`, { method: "GET" });
}
