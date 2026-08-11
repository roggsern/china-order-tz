import * as SecureStore from 'expo-secure-store';

const PENDING_CHECKOUT_KEY = 'cotz.checkout.pending_context';

/** Default TTL for unfinished checkout recovery (24h). */
export const CHECKOUT_RECOVERY_TTL_MS = 24 * 60 * 60 * 1000;

/**
 * Minimal recovery context after process death.
 * Bound to authenticated userId when known — never stores secrets.
 */
export type PendingCheckoutContext = {
  userId: string | null;
  checkoutSessionId: string;
  /** ISO timestamp when context was saved */
  updatedAt: string;
  /** Optional refs once order/payment started — never secrets */
  orderId: string | null;
  paymentTransactionId: string | null;
};

export type PendingCheckoutContextInput = Omit<PendingCheckoutContext, 'updatedAt'> & {
  updatedAt?: string;
};

export type PendingCheckoutContextStorage = {
  save: (context: PendingCheckoutContextInput) => Promise<void>;
  read: () => Promise<PendingCheckoutContext | null>;
  /**
   * Returns context only when still within TTL and has a session id.
   * Clears and returns null when expired or corrupt.
   */
  readValid: (nowMs?: number, ttlMs?: number) => Promise<PendingCheckoutContext | null>;
  /**
   * After login/register: keep only when same bound user.
   * Clears different-user and unbound/legacy contexts (no silent stamp).
   */
  bindToAuthenticatedUser: (userId: string) => Promise<PendingCheckoutContext | null>;
  clear: () => Promise<void>;
};

function normalize(value: string | null | undefined): string | null {
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function isExpired(updatedAt: string, nowMs: number, ttlMs: number): boolean {
  const saved = Date.parse(updatedAt);
  if (!Number.isFinite(saved)) return true;
  return nowMs - saved > ttlMs;
}

export const pendingCheckoutContextStorage: PendingCheckoutContextStorage = {
  async save(context): Promise<void> {
    const checkoutSessionId = normalize(context.checkoutSessionId);
    if (!checkoutSessionId) {
      await SecureStore.deleteItemAsync(PENDING_CHECKOUT_KEY);
      return;
    }

    const payload: PendingCheckoutContext = {
      userId: normalize(context.userId),
      checkoutSessionId,
      orderId: normalize(context.orderId),
      paymentTransactionId: normalize(context.paymentTransactionId),
      updatedAt: context.updatedAt ?? new Date().toISOString(),
    };

    await SecureStore.setItemAsync(PENDING_CHECKOUT_KEY, JSON.stringify(payload));
  },

  async read(): Promise<PendingCheckoutContext | null> {
    const raw = await SecureStore.getItemAsync(PENDING_CHECKOUT_KEY);
    if (!raw?.trim()) return null;
    try {
      const parsed = JSON.parse(raw) as Record<string, unknown>;
      const checkoutSessionId = normalize(
        typeof parsed.checkoutSessionId === 'string'
          ? parsed.checkoutSessionId
          : null,
      );
      if (!checkoutSessionId) return null;
      return {
        userId: normalize(typeof parsed.userId === 'string' ? parsed.userId : null),
        checkoutSessionId,
        orderId: normalize(
          typeof parsed.orderId === 'string' ? parsed.orderId : null,
        ),
        paymentTransactionId: normalize(
          typeof parsed.paymentTransactionId === 'string'
            ? parsed.paymentTransactionId
            : null,
        ),
        updatedAt:
          typeof parsed.updatedAt === 'string'
            ? parsed.updatedAt
            : new Date(0).toISOString(),
      };
    } catch {
      return null;
    }
  },

  async readValid(
    nowMs = Date.now(),
    ttlMs = CHECKOUT_RECOVERY_TTL_MS,
  ): Promise<PendingCheckoutContext | null> {
    const context = await pendingCheckoutContextStorage.read();
    if (!context) return null;
    if (isExpired(context.updatedAt, nowMs, ttlMs)) {
      await pendingCheckoutContextStorage.clear();
      return null;
    }
    return context;
  },

  async bindToAuthenticatedUser(userId: string): Promise<PendingCheckoutContext | null> {
    const normalizedUserId = normalize(userId);
    if (!normalizedUserId) {
      await pendingCheckoutContextStorage.clear();
      return null;
    }

    const context = await pendingCheckoutContextStorage.readValid();
    if (!context) return null;

    // Unbound / legacy — never stamp onto a new session.
    if (!context.userId) {
      await pendingCheckoutContextStorage.clear();
      return null;
    }

    if (context.userId !== normalizedUserId) {
      await pendingCheckoutContextStorage.clear();
      return null;
    }

    return context;
  },

  async clear(): Promise<void> {
    await SecureStore.deleteItemAsync(PENDING_CHECKOUT_KEY);
  },
};

/** True when server session can still be continued. */
export function isRecoverableCheckoutSession(session: {
  status?: string | null;
  isExpired?: boolean;
} | null | undefined): boolean {
  if (!session) return false;
  if (session.isExpired) return false;
  const status = (session.status ?? '').toLowerCase();
  if (status === 'expired' || status === 'completed' || status === 'cancelled') {
    return false;
  }
  return true;
}
