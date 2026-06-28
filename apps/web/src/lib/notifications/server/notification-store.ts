import type { Notification } from "@/lib/notifications/types";
import { normalizeUserId } from "@/lib/notifications/user-id";

const NOTIFICATIONS_HASH_KEY = "customer:notifications";
const MAX_NOTIFICATIONS_PER_USER = 100;

function hasRedisBackend(): boolean {
  return Boolean(
    process.env.UPSTASH_REDIS_REST_URL?.trim() && process.env.UPSTASH_REDIS_REST_TOKEN?.trim(),
  );
}

declare global {
  var __chinaOrderTzNotificationStore: Map<string, Notification[]> | undefined;
}

function getMemoryStore(): Map<string, Notification[]> {
  if (!globalThis.__chinaOrderTzNotificationStore) {
    globalThis.__chinaOrderTzNotificationStore = new Map();
  }
  return globalThis.__chinaOrderTzNotificationStore;
}

async function redisCommand<T>(command: string[]): Promise<T | null> {
  const url = process.env.UPSTASH_REDIS_REST_URL?.trim();
  const token = process.env.UPSTASH_REDIS_REST_TOKEN?.trim();

  if (!url || !token) {
    return null;
  }

  try {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(command),
      cache: "no-store",
    });

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { result?: T };
    return payload.result ?? null;
  } catch {
    return null;
  }
}

async function readRedisNotifications(userId: string): Promise<Notification[] | null> {
  const raw = await redisCommand<string>(["HGET", NOTIFICATIONS_HASH_KEY, userId]);
  if (!raw) {
    return [];
  }

  try {
    const parsed = JSON.parse(raw) as Notification[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

async function writeRedisNotifications(userId: string, notifications: Notification[]): Promise<boolean> {
  const saved = await redisCommand<number>([
    "HSET",
    NOTIFICATIONS_HASH_KEY,
    userId,
    JSON.stringify(notifications),
  ]);
  return saved !== null;
}

function sortNotifications(notifications: Notification[]): Notification[] {
  return [...notifications].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

export async function listStoredNotifications(userId: string): Promise<Notification[]> {
  const normalizedUserId = normalizeUserId(userId);

  if (hasRedisBackend()) {
    const redisNotifications = await readRedisNotifications(normalizedUserId);
    if (redisNotifications !== null) {
      getMemoryStore().set(normalizedUserId, redisNotifications);
      return sortNotifications(redisNotifications);
    }
  }

  return sortNotifications(getMemoryStore().get(normalizedUserId) ?? []);
}

async function persistNotifications(userId: string, notifications: Notification[]): Promise<void> {
  const normalizedUserId = normalizeUserId(userId);
  const trimmed = sortNotifications(notifications).slice(0, MAX_NOTIFICATIONS_PER_USER);
  getMemoryStore().set(normalizedUserId, trimmed);

  if (hasRedisBackend()) {
    await writeRedisNotifications(normalizedUserId, trimmed);
  }
}

export async function appendStoredNotification(notification: Notification): Promise<Notification | null> {
  const userId = normalizeUserId(notification.userId);
  const existing = await listStoredNotifications(userId);

  if (notification.dedupeKey && existing.some((entry) => entry.dedupeKey === notification.dedupeKey)) {
    return null;
  }

  const next = [{ ...notification, userId }, ...existing];
  await persistNotifications(userId, next);
  return { ...notification, userId };
}

export async function markStoredNotificationRead(
  userId: string,
  notificationId: string,
): Promise<Notification | null> {
  const normalizedUserId = normalizeUserId(userId);
  const existing = await listStoredNotifications(normalizedUserId);
  const index = existing.findIndex((entry) => entry.id === notificationId);

  if (index < 0) {
    return null;
  }

  const updated: Notification = { ...existing[index], isRead: true };
  const next = [...existing];
  next[index] = updated;
  await persistNotifications(normalizedUserId, next);
  return updated;
}

export async function markAllStoredNotificationsRead(userId: string): Promise<number> {
  const normalizedUserId = normalizeUserId(userId);
  const existing = await listStoredNotifications(normalizedUserId);
  let changed = 0;

  const next = existing.map((entry) => {
    if (entry.isRead) {
      return entry;
    }
    changed += 1;
    return { ...entry, isRead: true };
  });

  await persistNotifications(normalizedUserId, next);
  return changed;
}

export function isPersistentNotificationStoreEnabled(): boolean {
  return hasRedisBackend();
}
