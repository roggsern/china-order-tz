import { NextResponse } from "next/server";
import {
  listNotifications,
  markAllNotificationsRead,
} from "@/lib/notifications/server/notification-service";
import { normalizeUserId } from "@/lib/notifications/user-id";

export async function POST(request: Request) {
  let body: { userId?: string };

  try {
    body = (await request.json()) as { userId?: string };
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  if (!body.userId?.trim()) {
    return NextResponse.json({ error: "userId is required." }, { status: 400 });
  }

  const userId = normalizeUserId(body.userId);
  const marked = await markAllNotificationsRead(userId);
  const result = await listNotifications(userId);

  return NextResponse.json({
    marked,
    unreadCount: result.unreadCount,
    notifications: result.notifications,
  });
}
