import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/stores/{id}/taxonomy-import-source */
export async function GET(
  request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  const searchParams = forwardAllowedSearchParams(request, ["department_id"]);
  return proxyAdminApiRequest(
    `/stores/${encodeURIComponent(id)}/taxonomy-import-source`,
    { method: "GET", searchParams },
  );
}
