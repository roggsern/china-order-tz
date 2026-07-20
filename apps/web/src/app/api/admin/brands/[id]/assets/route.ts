import { proxyAdminMultipartRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/brands/{id}/assets — multipart fields `field` + `file` */
export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();

  if (!trimmed) {
    return Response.json(
      { success: false, message: "Brand id is required." },
      { status: 422 },
    );
  }

  const incoming = await request.formData();
  const field = incoming.get("field");
  const file = incoming.get("file") ?? incoming.get("image");

  if (field !== "logo" && field !== "banner") {
    return Response.json(
      { success: false, message: "Field must be logo or banner." },
      { status: 422 },
    );
  }

  if (!(file instanceof File)) {
    return Response.json(
      { success: false, message: "Image file is required (field: file)." },
      { status: 422 },
    );
  }

  const formData = new FormData();
  formData.append("field", field);
  formData.append("file", file, file.name);

  return proxyAdminMultipartRequest(
    `/brands/${encodeURIComponent(trimmed)}/assets`,
    formData,
    "POST",
  );
}
