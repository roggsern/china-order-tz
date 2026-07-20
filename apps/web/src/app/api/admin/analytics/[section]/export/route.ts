import {
  forwardAllowedSearchParams,
  proxyAdminBinaryRequest,
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
  "format",
] as const;

/** GET /api/admin/analytics/:section/export → Laravel /analytics/:type/export */
export async function GET(
  request: Request,
  context: { params: Promise<{ section: string }> },
) {
  const { section } = await context.params;
  const searchParams = forwardAllowedSearchParams(request, [...FILTER_KEYS]);
  return proxyAdminBinaryRequest(`/analytics/${section}/export`, { searchParams });
}
