import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ job: string }>;
};

/** GET /api/admin/warehouse/[job] → Laravel GET /api/v1/admin/warehouse/{job} */
export async function GET(_request: Request, context: RouteContext) {
  const { job } = await context.params;
  return proxyAdminApiRequest(`/warehouse/${encodeURIComponent(job)}`, {
    method: "GET",
  });
}
