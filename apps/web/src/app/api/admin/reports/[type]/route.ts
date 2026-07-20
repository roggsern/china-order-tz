import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";
import { isAdminReportType } from "@/lib/api/admin-reporting";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ type: string }>;
};

/** GET /api/admin/reports/:type → Laravel GET /api/v1/admin/reports/:type */
export async function GET(request: Request, context: RouteContext) {
  const { type } = await context.params;
  const trimmed = type?.trim().toLowerCase() ?? "";

  if (!isAdminReportType(trimmed)) {
    return NextResponse.json(
      { success: false, message: "Unknown report type." },
      { status: 404 },
    );
  }

  const searchParams = forwardAllowedSearchParams(request, ["from", "to"]);
  return proxyAdminApiRequest(`/reports/${encodeURIComponent(trimmed)}`, {
    method: "GET",
    searchParams,
  });
}
