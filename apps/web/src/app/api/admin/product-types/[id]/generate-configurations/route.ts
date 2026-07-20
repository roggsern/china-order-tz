import {
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/product-types/[id]/generate-configurations */
export async function POST(request: Request, context: RouteContext) {
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
    `/product-types/${encodeURIComponent(id)}/generate-configurations`,
    { method: "POST", body },
  );
}
