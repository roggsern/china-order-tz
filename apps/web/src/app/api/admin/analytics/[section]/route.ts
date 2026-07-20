import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

const FILTER_KEYS = [
  "from",
  "to",
  "store_id",
  "cashier_id",
  "customer_id",
  "category_id",
  "product_id",
  "payment_method",
  "promotion_id",
  "return_reason_id",
  "pos_only",
] as const;

const SECTIONS = new Set([
  "dashboard",
  "sales",
  "profit",
  "payments",
  "inventory",
  "returns",
  "customers",
  "promotions",
  "stores",
  "sessions",
]);

/** GET /api/admin/analytics/:section → Laravel /analytics/:section */
export async function GET(
  request: Request,
  context: { params: Promise<{ section: string }> },
) {
  const { section } = await context.params;
  if (!SECTIONS.has(section)) {
    return Response.json({ success: false, message: "Unknown analytics section." }, { status: 404 });
  }
  const searchParams = forwardAllowedSearchParams(request, [...FILTER_KEYS]);
  return proxyAdminApiRequest(`/analytics/${section}`, { method: "GET", searchParams });
}
