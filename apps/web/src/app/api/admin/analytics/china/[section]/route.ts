import { forwardAllowedSearchParams, proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ section: string }> };

export async function GET(request: Request, context: Ctx) {
  const { section } = await context.params;
  const searchParams = forwardAllowedSearchParams(request, ["from", "to", "limit"]);
  return proxyAdminApiRequest(`/analytics/china/${section}`, { method: "GET", searchParams });
}
