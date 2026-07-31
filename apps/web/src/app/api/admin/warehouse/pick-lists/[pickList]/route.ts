import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ pickList: string }> };

export async function GET(_req: Request, ctx: Ctx) {
  const { pickList } = await ctx.params;
  return proxyAdminApiRequest(`/warehouse/pick-lists/${encodeURIComponent(pickList)}`, { method: "GET" });
}
