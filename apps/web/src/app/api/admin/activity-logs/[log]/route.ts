import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ log: string }> };

/** GET /api/admin/activity-logs/[log] */
export async function GET(_request: Request, context: Ctx) {
  const { log } = await context.params;
  return proxyAdminApiRequest(`/activity-logs/${encodeURIComponent(log)}`, {
    method: "GET",
  });
}
