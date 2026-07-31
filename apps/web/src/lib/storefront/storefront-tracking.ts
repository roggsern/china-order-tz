import type { StorefrontVisitorIdentity } from "@/lib/storefront/visitor-identity";

export const STOREFRONT_EVENT_TYPES = [
  "page_view",
  "product_viewed",
  "search_performed",
  "add_to_cart",
  "checkout_started",
  "payment_started",
] as const;

export type StorefrontEventType = (typeof STOREFRONT_EVENT_TYPES)[number];

export type StorefrontEventPayload = {
  visitorUuid: string;
  sessionId: string;
  eventType: StorefrontEventType;
  path?: string;
  productId?: string;
  categoryId?: string;
  metadata?: Record<string, string | number | boolean | null>;
};

export function buildStorefrontEventDedupeKey(payload: Pick<
  StorefrontEventPayload,
  "eventType" | "path" | "productId" | "metadata"
>): string {
  const query =
    payload.metadata && typeof payload.metadata.query === "string"
      ? payload.metadata.query
      : "";

  return [
    payload.eventType,
    payload.path ?? "",
    payload.productId ?? "",
    query,
  ].join("|");
}

export function shouldSkipDuplicateStorefrontEvent(
  lastKey: string | null,
  nextKey: string,
): boolean {
  return lastKey === nextKey;
}

export function buildStorefrontEventRequestBody(
  identity: StorefrontVisitorIdentity,
  payload: Omit<StorefrontEventPayload, "visitorUuid" | "sessionId">,
): Record<string, unknown> {
  return {
    visitor_uuid: identity.visitorUuid,
    session_id: identity.sessionId,
    event_type: payload.eventType,
    path: payload.path,
    product_id: payload.productId,
    category_id: payload.categoryId,
    metadata: payload.metadata,
  };
}

export function isStorefrontTrackingEnabled(pathname: string): boolean {
  return !pathname.startsWith("/admin");
}
