import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ job: string }>;
};

/** PATCH /api/admin/warehouse/[job]/assign-packer */
export async function PATCH(request: Request, context: RouteContext) {
  const { job } = await context.params;
  const body = await request.text();
  return proxyAdminApiRequest(
    `/warehouse/${encodeURIComponent(job)}/assign-packer`,
    {
      method: "PATCH",
      body,
    },
  );
}
