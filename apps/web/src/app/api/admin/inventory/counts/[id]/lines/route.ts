import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function POST(request: Request, context: { params: Promise<{ id: string }> }) {
  const { id } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest(`/inventory/counts/${id}/lines`, { method: "POST", body });
}
