import {
  createVisitorUuid,
  mapIdentifyResponse,
  resolveVisitorIdentitySeed,
  shouldSkipDuplicateIdentify,
  type StorefrontVisitorIdentity,
} from "@/lib/storefront/visitor-identity";

export class StorefrontVisitorIdentityApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "StorefrontVisitorIdentityApiError";
  }
}

export async function identifyStorefrontVisitor(input: {
  visitorUuid?: string;
  sessionId?: string;
  token?: string | null;
}): Promise<StorefrontVisitorIdentity> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };

  if (input.token) {
    headers.Authorization = `Bearer ${input.token}`;
  }

  const response = await fetch("/api/storefront/visitor/identify", {
    method: "POST",
    credentials: "include",
    headers,
    body: JSON.stringify({
      visitor_uuid: input.visitorUuid,
      session_id: input.sessionId,
    }),
  });

  const payload = (await response.json().catch(() => ({}))) as {
    success?: boolean;
    data?: {
      visitor_id?: string;
      session_id?: string;
      visitor_uuid?: string;
    };
    message?: string;
  };

  if (!response.ok || !payload.data?.visitor_id || !payload.data.session_id || !payload.data.visitor_uuid) {
    throw new StorefrontVisitorIdentityApiError(
      payload.message?.trim() || "Unable to identify storefront visitor.",
      response.status,
    );
  }

  return mapIdentifyResponse({
    visitor_id: payload.data.visitor_id,
    session_id: payload.data.session_id,
    visitor_uuid: payload.data.visitor_uuid,
  });
}

let identifyPromise: Promise<StorefrontVisitorIdentity> | null = null;

export async function ensureStorefrontVisitorIdentity(options?: {
  existing?: StorefrontVisitorIdentity | null;
  token?: string | null;
  identify?: typeof identifyStorefrontVisitor;
}): Promise<StorefrontVisitorIdentity> {
  const identify = options?.identify ?? identifyStorefrontVisitor;
  const seed = resolveVisitorIdentitySeed(options?.existing ?? null);

  if (identifyPromise) {
    return identifyPromise;
  }

  identifyPromise = identify({
    visitorUuid: seed.visitorUuid,
    sessionId: seed.sessionId,
    token: options?.token ?? null,
  }).finally(() => {
    identifyPromise = null;
  });

  return identifyPromise;
}

export { createVisitorUuid };
