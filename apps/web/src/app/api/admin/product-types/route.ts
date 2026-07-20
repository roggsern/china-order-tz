import {
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/product-types → Laravel GET /api/v1/admin/product-types */
export async function GET() {
  return proxyAdminApiRequest("/product-types", { method: "GET" });
}
