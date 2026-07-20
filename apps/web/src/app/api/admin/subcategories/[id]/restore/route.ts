import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/subcategories/{id}/restore */
export async function POST(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Subcategory id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(
    `/subcategories/${encodeURIComponent(trimmed)}/restore`,
    { method: "POST" },
  );
}
