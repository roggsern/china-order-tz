import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(_request: Request, context: { params: Promise<{ id: string }> }) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/inventory/counts/${id}/submit`, { method: "POST" });
}
