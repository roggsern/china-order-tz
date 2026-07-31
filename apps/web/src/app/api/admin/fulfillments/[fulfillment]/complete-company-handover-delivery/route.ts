import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";

type RouteContext = {
  params: Promise<{ fulfillment: string }>;
};

/** POST /api/admin/fulfillments/[fulfillment]/complete-company-handover-delivery */
export async function POST(_request: Request, context: RouteContext) {
  const { fulfillment } = await context.params;

  return proxyAdminApiRequest(
    `/fulfillments/${encodeURIComponent(fulfillment)}/complete-company-handover-delivery`,
    { method: "POST" },
  );
}
