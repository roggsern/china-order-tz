import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/departments/{id}/restore */
export async function POST(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Department id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/departments/${encodeURIComponent(trimmed)}/restore`, {
    method: "POST",
  });
}
