import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ transfer: string }> };

export async function POST(_req: Request, ctx: Ctx) {
  const { transfer } = await ctx.params;
  return proxyAdminApiRequest(`/warehouse/transfers/${encodeURIComponent(transfer)}/approve`, { method: "POST", body: {} });
}
