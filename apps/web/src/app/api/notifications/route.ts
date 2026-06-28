import { NextResponse } from "next/server";
import { listNotifications } from "@/lib/notifications/server/notification-service";
import { normalizeUserId } from "@/lib/notifications/user-id";

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const userId = searchParams.get("userId");

  if (!userId?.trim()) {
    return NextResponse.json({ error: "userId is required." }, { status: 400 });
  }

  const result = await listNotifications(normalizeUserId(userId));
  return NextResponse.json(result);
}
