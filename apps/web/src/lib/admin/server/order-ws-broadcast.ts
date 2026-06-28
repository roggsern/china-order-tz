import type { AdminOrderWsEvent } from "@/lib/admin/order-ws-types";

type WsClient = {
  readyState: number;
  send: (data: string) => void;
  on: (event: "close" | "error", listener: () => void) => void;
};

declare global {
  var __chinaOrderTzAdminWsClients: Set<WsClient> | undefined;
}

function getClients(): Set<WsClient> {
  if (!globalThis.__chinaOrderTzAdminWsClients) {
    globalThis.__chinaOrderTzAdminWsClients = new Set();
  }
  return globalThis.__chinaOrderTzAdminWsClients;
}

/** Broadcast to in-process WebSocket clients (custom Node server only). */
export function broadcastAdminOrderEvent(event: AdminOrderWsEvent): void {
  const payload = JSON.stringify(event);

  for (const client of getClients()) {
    if (client.readyState === 1) {
      client.send(payload);
    }
  }
}

/** Register a WebSocket client — call only from the custom dev/self-hosted WS server. */
export function registerAdminOrderWsClient(ws: WsClient): void {
  const clients = getClients();
  clients.add(ws);

  const clientId = crypto.randomUUID();
  ws.send(JSON.stringify({ type: "connected", clientId } satisfies AdminOrderWsEvent));

  ws.on("close", () => {
    clients.delete(ws);
  });

  ws.on("error", () => {
    clients.delete(ws);
  });
}

export function getAdminOrderWsClientCount(): number {
  return getClients().size;
}

/** Optional Redis pub for external realtime services. */
export async function publishAdminOrderRedisEvent(event: AdminOrderWsEvent): Promise<void> {
  const url = process.env.UPSTASH_REDIS_REST_URL?.trim();
  const token = process.env.UPSTASH_REDIS_REST_TOKEN?.trim();
  const channel = process.env.ADMIN_ORDERS_REDIS_CHANNEL?.trim() ?? "admin:orders:events";

  if (!url || !token) {
    return;
  }

  try {
    await fetch(url, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(["PUBLISH", channel, JSON.stringify(event)]),
      cache: "no-store",
    });
  } catch {
    // Non-blocking — polling and REST remain available.
  }
}
