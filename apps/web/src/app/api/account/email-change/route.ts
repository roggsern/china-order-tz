import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function POST(request: Request) {
  return proxyCustomerApiRequest(request, "/account/email-change", { method: "POST" });
}

export async function GET(request: Request) {
  return proxyCustomerApiRequest(request, "/account/email-change/pending", { method: "GET" });
}
