import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** GET /api/admin/commerce-channels/:id → Laravel GET /api/v1/admin/commerce-channels/:id */
export async function GET(
  _request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;

  return proxyAdminApiRequest(`/commerce-channels/${id}`, { method: "GET" });
}
