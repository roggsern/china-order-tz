import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/fulfillments/assignees → Laravel GET .../fulfillments/assignees */
export async function GET() {
  return proxyAdminApiRequest("/fulfillments/assignees", { method: "GET" });
}
