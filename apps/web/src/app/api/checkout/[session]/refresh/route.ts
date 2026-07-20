import { proxyCustomerApiRequest } from "@/lib/api/bff-upstream";

type RouteContext = {
  params: Promise<{ session: string }>;
};

export async function POST(request: Request, context: RouteContext) {
  const { session } = await context.params;
  return proxyCustomerApiRequest(
    request,
    `/checkout/${encodeURIComponent(session)}/refresh`,
    { method: "POST" },
  );
}
