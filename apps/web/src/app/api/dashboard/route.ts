import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

/** BFF proxy for GET /api/v1/dashboard — used for efficient My Orders badge counts. */
export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/dashboard", { method: "GET" });
}
