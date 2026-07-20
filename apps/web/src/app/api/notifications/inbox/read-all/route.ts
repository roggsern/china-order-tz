import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

/** POST /api/notifications/inbox/read-all → Laravel */
export async function POST(request: Request) {
  return proxyCustomerApiRequest(request, "/notifications/read-all", {
    method: "POST",
  });
}
