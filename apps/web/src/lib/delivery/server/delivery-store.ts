import type { Delivery } from "@/lib/delivery/types";
import { DELIVERY_STATUS } from "@/lib/delivery/types";

const DELIVERIES_HASH_KEY = "admin:deliveries";

function hasRedisBackend(): boolean {
  return Boolean(
    process.env.UPSTASH_REDIS_REST_URL?.trim() && process.env.UPSTASH_REDIS_REST_TOKEN?.trim(),
  );
}

declare global {
  var __chinaOrderTzDeliveryStore: Map<string, Delivery> | undefined;
}

function getMemoryStore(): Map<string, Delivery> {
  if (!globalThis.__chinaOrderTzDeliveryStore) {
    globalThis.__chinaOrderTzDeliveryStore = new Map();
  }
  return globalThis.__chinaOrderTzDeliveryStore;
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

async function listRedisDeliveries(): Promise<Delivery[] | null> {
  const entries = await redisCommand<string[]>(["HGETALL", DELIVERIES_HASH_KEY]);
  if (!entries) {
    return null;
  }

  const deliveries: Delivery[] = [];
  for (let index = 0; index < entries.length; index += 2) {
    const raw = entries[index + 1];
    if (!raw) {
      continue;
    }

    try {
      deliveries.push(JSON.parse(raw) as Delivery);
    } catch {
      // Skip malformed entries.
    }
  }

  return deliveries.sort((a, b) => b.updatedAt.localeCompare(a.updatedAt));
}

async function getRedisDelivery(orderId: string): Promise<Delivery | null> {
  const raw = await redisCommand<string>(["HGET", DELIVERIES_HASH_KEY, orderId]);
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as Delivery;
  } catch {
    return null;
  }
}

async function upsertRedisDelivery(delivery: Delivery): Promise<boolean> {
  const saved = await redisCommand<number>([
    "HSET",
    DELIVERIES_HASH_KEY,
    delivery.orderId,
    JSON.stringify(delivery),
  ]);
  return saved !== null;
}

export async function listStoredDeliveries(): Promise<Delivery[]> {
  if (hasRedisBackend()) {
    const redisDeliveries = await listRedisDeliveries();
    if (redisDeliveries) {
      for (const delivery of redisDeliveries) {
        getMemoryStore().set(delivery.orderId, delivery);
      }
      return redisDeliveries;
    }
  }

  return [...getMemoryStore().values()].sort((a, b) => b.updatedAt.localeCompare(a.updatedAt));
}

export async function getStoredDelivery(orderId: string): Promise<Delivery | null> {
  if (hasRedisBackend()) {
    const redisDelivery = await getRedisDelivery(orderId);
    if (redisDelivery) {
      getMemoryStore().set(orderId, redisDelivery);
      return redisDelivery;
    }
  }

  return getMemoryStore().get(orderId) ?? null;
}

export async function upsertStoredDelivery(delivery: Delivery): Promise<void> {
  getMemoryStore().set(delivery.orderId, delivery);

  if (hasRedisBackend()) {
    await upsertRedisDelivery(delivery);
  }
}

export async function listActiveDeliveries(): Promise<Delivery[]> {
  const deliveries = await listStoredDeliveries();
  return deliveries.filter((delivery) => delivery.status !== DELIVERY_STATUS.DELIVERED);
}
