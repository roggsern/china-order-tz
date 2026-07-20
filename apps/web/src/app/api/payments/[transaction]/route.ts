import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ transaction: string }>;
};

export async function GET(request: Request, context: RouteContext) {
  const { transaction } = await context.params;
  return proxyCustomerApiRequest(
    request,
    `/payments/${encodeURIComponent(transaction)}`,
    { method: "GET" },
  );
}
