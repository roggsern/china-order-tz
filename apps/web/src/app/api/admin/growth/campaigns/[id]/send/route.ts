import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(_request: Request, context: { params: Promise<{ id: string }> }) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/growth/campaigns/${id}/send`, { method: "POST" });
}
