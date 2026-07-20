import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";

/** GET /api/admin/activity-logs → Laravel GET /api/v1/admin/activity-logs */
export async function GET(request: Request) {
  const searchParams = forwardAllowedSearchParams(request, [
    "page",
    "per_page",
    "event_type",
    "actor_type",
    "actor_id",
    "subject_type",
    "subject_id",
    "search",
    "date_from",
    "date_to",
  ]);
  return proxyAdminApiRequest("/activity-logs", { method: "GET", searchParams });
}
