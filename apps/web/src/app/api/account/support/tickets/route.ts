import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/account/support/tickets", { method: "GET" });
}

export async function POST(request: Request) {
  const body = await request.json();
  return proxyCustomerApiRequest(request, "/account/support/tickets", { method: "POST", body });
}
