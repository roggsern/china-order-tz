import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ packing: string; line: string }> };

export async function PATCH(request: Request, ctx: Ctx) {
  const { packing, line } = await ctx.params;
  const body = await request.json();
  return proxyAdminApiRequest(
    `/warehouse/packing/${encodeURIComponent(packing)}/lines/${encodeURIComponent(line)}`,
    { method: "PATCH", body },
  );
}
