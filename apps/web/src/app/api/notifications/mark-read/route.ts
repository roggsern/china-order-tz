import { NextResponse } from "next/server";
import { markNotificationRead } from "@/lib/notifications/server/notification-service";
import { normalizeUserId } from "@/lib/notifications/user-id";

export async function POST(request: Request) {
  let body: { userId?: string; id?: string };

  try {
    body = (await request.json()) as { userId?: string; id?: string };
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  if (!body.userId?.trim() || !body.id?.trim()) {
    return NextResponse.json({ error: "userId and id are required." }, { status: 400 });
  }

  const updated = await markNotificationRead(normalizeUserId(body.userId), body.id);
  if (!updated) {
    return NextResponse.json({ error: "Notification not found." }, { status: 404 });
  }

  return NextResponse.json({ notification: updated });
}
