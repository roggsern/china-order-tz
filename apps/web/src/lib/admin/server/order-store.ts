import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";

const ORDERS_HASH_KEY = "admin:orders";

function hasRedisBackend(): boolean {
  return Boolean(
    process.env.UPSTASH_REDIS_REST_URL?.trim() && process.env.UPSTASH_REDIS_REST_TOKEN?.trim(),
  );
}

declare global {
  var __chinaOrderTzAdminOrderStore: Map<string, Order> | undefined;
}

function getMemoryStore(): Map<string, Order> {
  if (!globalThis.__chinaOrderTzAdminOrderStore) {
    globalThis.__chinaOrderTzAdminOrderStore = new Map();
  }
  return globalThis.__chinaOrderTzAdminOrderStore;
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

async function listRedisOrders(): Promise<Order[] | null> {
  const entries = await redisCommand<string[]>(["HGETALL", ORDERS_HASH_KEY]);
  if (!entries) {
    return null;
  }

  const orders: Order[] = [];
  for (let index = 0; index < entries.length; index += 2) {
    const raw = entries[index + 1];
    if (!raw) {
      continue;
    }

    try {
      orders.push(normalizeOrder(JSON.parse(raw) as Order));
    } catch {
      // Skip malformed entries.
    }
  }

  return orders.sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

async function getRedisOrder(orderId: string): Promise<Order | null> {
  const raw = await redisCommand<string>(["HGET", ORDERS_HASH_KEY, orderId]);
  if (!raw) {
    return null;
  }

  try {
    return normalizeOrder(JSON.parse(raw) as Order);
  } catch {
    return null;
  }
}

async function upsertRedisOrder(order: Order): Promise<"created" | "updated" | null> {
  const normalized = normalizeOrder(order);
  const existed = await getRedisOrder(normalized.id);
  const saved = await redisCommand<number>([
    "HSET",
    ORDERS_HASH_KEY,
    normalized.id,
    JSON.stringify(normalized),
  ]);

  if (saved === null) {
    return null;
  }

  return existed ? "updated" : "created";
}

export function isPersistentOrderStoreEnabled(): boolean {
  return hasRedisBackend();
}

export async function listStoredOrders(): Promise<Order[]> {
  if (hasRedisBackend()) {
    const redisOrders = await listRedisOrders();
    if (redisOrders) {
      return redisOrders;
    }
  }

  return [...getMemoryStore().values()].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

export async function getStoredOrder(orderId: string): Promise<Order | null> {
  if (hasRedisBackend()) {
    const redisOrder = await getRedisOrder(orderId);
    if (redisOrder) {
      return redisOrder;
    }
  }

  return getMemoryStore().get(orderId) ?? null;
}

export async function upsertStoredOrder(order: Order): Promise<"created" | "updated"> {
  const normalized = normalizeOrder(order);

  if (hasRedisBackend()) {
    const redisAction = await upsertRedisOrder(normalized);
    if (redisAction) {
      getMemoryStore().set(normalized.id, normalized);
      return redisAction;
    }
  }

  const existed = getMemoryStore().has(normalized.id);
  getMemoryStore().set(normalized.id, normalized);
  return existed ? "updated" : "created";
}

export async function patchStoredOrder(
  orderId: string,
  patch: Partial<Order>,
): Promise<Order | null> {
  const existing = (await getStoredOrder(orderId)) ?? null;
  if (!existing) {
    return null;
  }

  const updated = normalizeOrder({
    ...existing,
    ...patch,
    orderNumber: existing.orderNumber,
  });

  await upsertStoredOrder(updated);
  return updated;
}
