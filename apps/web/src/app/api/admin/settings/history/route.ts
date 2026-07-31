import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, ["event", "per_page", "page"]);
  return proxyAdminApiRequest("/settings/history", { method: "GET", searchParams });
}
