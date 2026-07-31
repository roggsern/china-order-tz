import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function PATCH(
  _request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/admins/${id}/deactivate`, { method: "PATCH" });
}
