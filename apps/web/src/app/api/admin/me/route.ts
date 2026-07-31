import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/me → Laravel GET /api/v1/admin/me */
export async function GET() {
  return proxyAdminApiRequest("/me", { method: "GET" });
}
