import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string; adminId: string }>;
};

export async function PUT(request: Request, context: RouteContext) {
  const { id, adminId } = await context.params;
  if (!id?.trim() || !adminId?.trim()) {
    return Response.json({ success: false, message: "Store and admin id are required." }, { status: 422 });
  }
  const body = await request.json();
  return proxyAdminApiRequest(
    `/stores/${encodeURIComponent(id)}/team/${encodeURIComponent(adminId)}`,
    { method: "PUT", body },
  );
}

export async function DELETE(_request: Request, context: RouteContext) {
  const { id, adminId } = await context.params;
  if (!id?.trim() || !adminId?.trim()) {
    return Response.json({ success: false, message: "Store and admin id are required." }, { status: 422 });
  }
  return proxyAdminApiRequest(
    `/stores/${encodeURIComponent(id)}/team/${encodeURIComponent(adminId)}`,
    { method: "DELETE" },
  );
}
