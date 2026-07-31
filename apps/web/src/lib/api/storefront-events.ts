import {
  buildStorefrontEventRequestBody,
  type StorefrontEventPayload,
} from "@/lib/storefront/storefront-tracking";
import type { StorefrontVisitorIdentity } from "@/lib/storefront/visitor-identity";

export class StorefrontEventApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "StorefrontEventApiError";
  }
}

export async function recordStorefrontEvent(
  identity: StorefrontVisitorIdentity,
  payload: Omit<StorefrontEventPayload, "visitorUuid" | "sessionId">,
  token?: string | null,
): Promise<{ id: string }> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch("/api/storefront/events", {
    method: "POST",
    credentials: "include",
    headers,
    body: JSON.stringify(buildStorefrontEventRequestBody(identity, payload)),
  });

  const body = (await response.json().catch(() => ({}))) as {
    success?: boolean;
    data?: { id?: string };
    message?: string;
  };

  if (!response.ok || !body.data?.id) {
    throw new StorefrontEventApiError(
      body.message?.trim() || "Unable to record storefront event.",
      response.status,
    );
  }

  return { id: body.data.id };
}

let inFlightEventKey: string | null = null;
let inFlightEventPromise: Promise<{ id: string }> | null = null;

export async function recordStorefrontEventDeduped(
  identity: StorefrontVisitorIdentity,
  payload: Omit<StorefrontEventPayload, "visitorUuid" | "sessionId">,
  dedupeKey: string,
  token?: string | null,
): Promise<{ id: string } | null> {
  if (inFlightEventKey === dedupeKey && inFlightEventPromise) {
    return inFlightEventPromise;
  }

  inFlightEventKey = dedupeKey;
  inFlightEventPromise = recordStorefrontEvent(identity, payload, token)
    .catch((error) => {
      throw error;
    })
    .finally(() => {
      if (inFlightEventKey === dedupeKey) {
        inFlightEventKey = null;
        inFlightEventPromise = null;
      }
    });

  return inFlightEventPromise;
}
