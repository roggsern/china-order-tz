import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ pickList: string; line: string }> };

export async function PATCH(request: Request, ctx: Ctx) {
  const { pickList, line } = await ctx.params;
  const body = await request.json();
  return proxyAdminApiRequest(
    `/warehouse/pick-lists/${encodeURIComponent(pickList)}/lines/${encodeURIComponent(line)}`,
    { method: "PATCH", body },
  );
}
