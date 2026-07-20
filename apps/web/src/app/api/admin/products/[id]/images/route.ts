import {
  proxyAdminApiRequest,
  proxyAdminMultipartRequest,
} from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** GET /api/admin/products/{id}/images */
export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/products/${encodeURIComponent(trimmed)}/images`, {
    method: "GET",
  });
}

/** POST /api/admin/products/{id}/images — multipart field `image` */
export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  const incoming = await request.formData();
  const file = incoming.get("image") ?? incoming.get("file");

  if (!(file instanceof File)) {
    return Response.json(
      { success: false, message: "Image file is required (field: image)." },
      { status: 422 },
    );
  }

  const formData = new FormData();
  formData.append("image", file, file.name);

  return proxyAdminMultipartRequest(
    `/products/${encodeURIComponent(trimmed)}/images`,
    formData,
    "POST",
  );
}
