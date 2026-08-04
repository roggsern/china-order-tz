import {
  proxyAdminApiRequest,
  proxyAdminMultipartRequest,
} from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/products/{id}/media/apply-to-attribute-option */
export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Product id is required." },
      { status: 422 },
    );
  }

  const path = `/products/${encodeURIComponent(trimmed)}/media/apply-to-attribute-option`;
  const contentType = request.headers.get("content-type") ?? "";

  if (contentType.includes("multipart/form-data")) {
    const incoming = await request.formData();
    const formData = new FormData();

    for (const [key, value] of incoming.entries()) {
      formData.append(key, value);
    }

    return proxyAdminMultipartRequest(path, formData, "POST");
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

  return proxyAdminApiRequest(path, {
    method: "POST",
    body,
  });
}
