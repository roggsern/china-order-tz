import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ id: string; noteId: string }> };

export async function PATCH(request: Request, { params }: Params) {
  const { id, noteId } = await params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyAdminApiRequest(`/customers/${id}/notes/${noteId}`, { method: "PATCH", body });
}

export async function DELETE(_request: Request, { params }: Params) {
  const { id, noteId } = await params;
  return proxyAdminApiRequest(`/customers/${id}/notes/${noteId}`, { method: "DELETE" });
}
