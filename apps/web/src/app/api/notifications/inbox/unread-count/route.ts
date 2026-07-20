import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

/** GET /api/notifications/inbox/unread-count → Laravel */
export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/notifications/unread-count", {
    method: "GET",
  });
}
