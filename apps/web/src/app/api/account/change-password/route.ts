import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function POST(request: Request) {
  return proxyCustomerApiRequest(request, "/account/change-password", { method: "POST" });
}
