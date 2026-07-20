import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ template: string }> };

/** GET /api/admin/notification-templates/[template] */
export async function GET(_request: Request, context: Ctx) {
  const { template } = await context.params;
  return proxyAdminApiRequest(
    `/notification-templates/${encodeURIComponent(template)}`,
    { method: "GET" },
  );
}

/** PUT /api/admin/notification-templates/[template] */
export async function PUT(request: Request, context: Ctx) {
  const { template } = await context.params;
  return proxyAdminApiRequest(
    `/notification-templates/${encodeURIComponent(template)}`,
    { method: "PUT", body: await request.json() },
  );
}
