import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ id: string; tagId: string }> };

export async function DELETE(_request: Request, { params }: Params) {
  const { id, tagId } = await params;
  return proxyAdminApiRequest(`/customers/${id}/tags/${tagId}`, { method: "DELETE" });
}
