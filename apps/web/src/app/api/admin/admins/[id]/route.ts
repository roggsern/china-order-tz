import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(
  _request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  return proxyAdminApiRequest(`/admins/${id}`, { method: "GET" });
}

export async function PATCH(
  request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }

  return proxyAdminApiRequest(`/admins/${id}`, { method: "PATCH", body });
}
