import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

/** GET /api/returns → Laravel GET /api/v1/returns */
export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/returns", { method: "GET" });
}
