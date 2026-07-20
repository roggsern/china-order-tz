import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Params = { params: Promise<{ id: string }> };

export async function POST(_request: Request, { params }: Params) {
  const { id } = await params;
  return proxyAdminApiRequest(`/customers/${id}/metrics/rebuild`, { method: "POST" });
}
