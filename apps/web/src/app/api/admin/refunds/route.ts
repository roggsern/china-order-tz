import { forwardAllowedSearchParams, proxyAdminApiRequest } from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "status",
    "order_id",
    "customer_id",
    "payment_id",
    "search",
  ]);

  return proxyAdminApiRequest("/refunds", { method: "GET", searchParams });
}

export async function POST(request: Request) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return Response.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }

  return proxyAdminApiRequest("/refunds", { method: "POST", body });
}
