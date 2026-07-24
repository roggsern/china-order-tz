import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/stores → Laravel GET /api/v1/admin/stores */
export async function GET() {
  return proxyAdminApiRequest("/stores", { method: "GET" });
}
