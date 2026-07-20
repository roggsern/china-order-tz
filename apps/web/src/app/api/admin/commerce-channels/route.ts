import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/commerce-channels → Laravel GET /api/v1/admin/commerce-channels */
export async function GET() {
  return proxyAdminApiRequest("/commerce-channels", { method: "GET" });
}
