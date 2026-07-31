import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ job: string }>;
};

/** PATCH /api/admin/warehouse/[job]/status */
export async function PATCH(request: Request, context: RouteContext) {
  const { job } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }
  return proxyAdminApiRequest(`/warehouse/${encodeURIComponent(job)}/status`, {
    method: "PATCH",
    body,
  });
}
