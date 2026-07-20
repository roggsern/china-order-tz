import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

export async function POST(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();
  if (!trimmed) {
    return Response.json(
      { success: false, message: "Attribute id is required." },
      { status: 422 },
    );
  }
  return proxyAdminApiRequest(
    `/catalog-attributes/${encodeURIComponent(trimmed)}/restore`,
    { method: "POST" },
  );
}
