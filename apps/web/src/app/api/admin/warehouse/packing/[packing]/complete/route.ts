import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ packing: string }> };

export async function POST(_req: Request, ctx: Ctx) {
  const { packing } = await ctx.params;
  return proxyAdminApiRequest(`/warehouse/packing/${encodeURIComponent(packing)}/complete`, { method: "POST", body: {} });
}
