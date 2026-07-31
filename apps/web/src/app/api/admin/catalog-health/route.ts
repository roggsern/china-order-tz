/** GET /api/admin/catalog-health → Laravel GET /api/v1/admin/catalog-health */
import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET() {
  return proxyAdminApiRequest("/catalog-health", { method: "GET" });
}
