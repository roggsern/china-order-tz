import {
  proxyAdminApiRequest,
  proxyAdminMultipartRequest,
} from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** GET /api/admin/products/{id}/media */
export async function GET(_request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/products/${encodeURIComponent(trimmed)}/media`, {
    method: "GET",
  });
}

/** POST /api/admin/products/{id}/media — JSON (video URL) or multipart (image file) */
export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  const contentType = request.headers.get("content-type") ?? "";

  if (contentType.includes("multipart/form-data")) {
    const incoming = await request.formData();
    const formData = new FormData();

    for (const [key, value] of incoming.entries()) {
      formData.append(key, value);
    }

    return proxyAdminMultipartRequest(
      `/products/${encodeURIComponent(trimmed)}/media`,
      formData,
      "POST",
    );
  }

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  return proxyAdminApiRequest(`/products/${encodeURIComponent(trimmed)}/media`, {
    method: "POST",
    body,
  });
}
