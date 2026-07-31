import { proxyAdminMultipartRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ id: string }>;
};

/** POST /api/admin/stores/{id}/branding — multipart logo/banner */
export async function POST(request: Request, context: RouteContext) {
  const { id } = await context.params;
  const trimmed = id?.trim();
  if (!trimmed) {
    return Response.json({ success: false, message: "Store id is required." }, { status: 422 });
  }

  const incoming = await request.formData();
  const logo = incoming.get("logo");
  const banner = incoming.get("banner");

  if (!(logo instanceof File) && !(banner instanceof File)) {
    return Response.json(
      { success: false, message: "Upload at least one branding image (logo or banner)." },
      { status: 422 },
    );
  }

  const formData = new FormData();
  if (logo instanceof File) formData.append("logo", logo, logo.name);
  if (banner instanceof File) formData.append("banner", banner, banner.name);

  return proxyAdminMultipartRequest(
    `/stores/${encodeURIComponent(trimmed)}/branding`,
    formData,
    "POST",
  );
}
