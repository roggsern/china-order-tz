export const VISITOR_IDENTITY_STORAGE_KEY = "china-order-tz-visitor-identity";

export type StorefrontVisitorIdentity = {
  visitorUuid: string;
  visitorId: string;
  sessionId: string;
};

export type IdentifyStorefrontVisitorPayload = {
  visitorUuid?: string;
  sessionId?: string;
};

export type IdentifyStorefrontVisitorResponse = {
  visitor_id: string;
  session_id: string;
  visitor_uuid: string;
};

export function createVisitorUuid(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
    const random = Math.floor(Math.random() * 16);
    const value = char === "x" ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}

export function loadVisitorIdentity(): StorefrontVisitorIdentity | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(VISITOR_IDENTITY_STORAGE_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as Partial<StorefrontVisitorIdentity>;
    if (!parsed.visitorUuid || !parsed.visitorId || !parsed.sessionId) {
      return null;
    }

    return {
      visitorUuid: parsed.visitorUuid,
      visitorId: parsed.visitorId,
      sessionId: parsed.sessionId,
    };
  } catch {
    return null;
  }
}

export function saveVisitorIdentity(identity: StorefrontVisitorIdentity): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(VISITOR_IDENTITY_STORAGE_KEY, JSON.stringify(identity));
}

export function clearVisitorIdentity(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(VISITOR_IDENTITY_STORAGE_KEY);
}

export function resolveVisitorIdentitySeed(
  existing: StorefrontVisitorIdentity | null,
): Pick<StorefrontVisitorIdentity, "visitorUuid"> & Partial<StorefrontVisitorIdentity> {
  if (existing) {
    return existing;
  }

  return {
    visitorUuid: createVisitorUuid(),
  };
}

export function mapIdentifyResponse(
  response: IdentifyStorefrontVisitorResponse,
): StorefrontVisitorIdentity {
  return {
    visitorUuid: response.visitor_uuid,
    visitorId: response.visitor_id,
    sessionId: response.session_id,
  };
}

export function shouldSkipDuplicateIdentify(inFlight: boolean): boolean {
  return inFlight;
}
