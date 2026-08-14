import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

/** POST /api/admin/stores/{id}/taxonomy-import */
export async function POST(
  request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(
    `/stores/${encodeURIComponent(id)}/taxonomy-import`,
    { method: "POST", body },
  );
}
