import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type Ctx = { params: Promise<{ template: string }> };

/** POST /api/admin/notification-templates/[template]/preview */
export async function POST(request: Request, context: Ctx) {
  const { template } = await context.params;
  return proxyAdminApiRequest(
    `/notification-templates/${encodeURIComponent(template)}/preview`,
    { method: "POST", body: await request.json() },
  );
}
