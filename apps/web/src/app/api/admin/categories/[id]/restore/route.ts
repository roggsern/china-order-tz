import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/categories/{id}/restore */
export async function POST(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Category id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/categories/${encodeURIComponent(trimmed)}/restore`, {
    method: "POST",
  });
}
