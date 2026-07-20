import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(request: Request, context: { params: Promise<{ id: string }> }) {
  const { id } = await context.params;
  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }
  return proxyAdminApiRequest(`/inventory/counts/${id}/approve`, { method: "POST", body });
}
