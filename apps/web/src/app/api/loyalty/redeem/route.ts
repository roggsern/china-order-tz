import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }
  return proxyCustomerApiRequest(request, "/loyalty/redeem", { method: "POST", body });
}
