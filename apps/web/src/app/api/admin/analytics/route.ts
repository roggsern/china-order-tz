import { NextResponse } from "next/server";
import { getAnalyticsSnapshot } from "@/lib/admin/server/analytics-hub";
import type { AnalyticsRangeDays } from "@/lib/admin/analytics";

function parseRangeDays(value: string | null): AnalyticsRangeDays {
  const parsed = Number.parseInt(value ?? "30", 10);
  if (parsed === 7 || parsed === 14) {
    return parsed;
  }
  return 30;
}

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const rangeDays = parseRangeDays(searchParams.get("rangeDays"));
  const snapshot = await getAnalyticsSnapshot(rangeDays);
  return NextResponse.json(snapshot);
}
