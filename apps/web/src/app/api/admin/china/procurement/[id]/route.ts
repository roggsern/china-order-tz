import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(
  _request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/china/procurement/${encodeURIComponent(id)}`, { method: "GET" });
}
