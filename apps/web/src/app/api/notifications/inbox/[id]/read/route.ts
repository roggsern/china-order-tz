import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

/** PATCH /api/notifications/inbox/[id]/read → Laravel */
export async function PATCH(
  request: Request,
  context: { params: Promise<{ id: string }> },
) {
  const { id } = await context.params;
  return proxyCustomerApiRequest(
    request,
    `/notifications/${encodeURIComponent(id)}/read`,
    { method: "PATCH" },
  );
}
