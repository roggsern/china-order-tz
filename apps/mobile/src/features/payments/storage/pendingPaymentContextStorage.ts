import * as SecureStore from 'expo-secure-store';

const PENDING_PAYMENT_KEY = 'cotz.payment.pending_context';

/** Default TTL for unfinished NMB payment recovery (72h). */
export const PAYMENT_RECOVERY_TTL_MS = 72 * 60 * 60 * 1000;

/**
 * Persisted across process death so cold-start payment-return can reconcile.
 * Bound to authenticated userId when known — never store secrets.
 */
export type PendingPaymentContext = {
  /** Owning user id when saved while authenticated. */
  userId: string | null;
  orderId: string | null;
  paymentTransactionId: string | null;
  merchantReference: string | null;
  successIndicator: string | null;
  /** Deep-link hint only — not proof of paid. */
  resultIndicator: string | null;
  checkoutSessionId: string | null;
  updatedAt: string;
};

export type PendingPaymentContextInput = Omit<PendingPaymentContext, 'updatedAt'> & {
  updatedAt?: string;
};

export type PendingPaymentContextStorage = {
  save: (context: PendingPaymentContextInput) => Promise<void>;
  merge: (partial: Partial<PendingPaymentContextInput>) => Promise<PendingPaymentContext | null>;
  read: () => Promise<PendingPaymentContext | null>;
  readValid: (nowMs?: number, ttlMs?: number) => Promise<PendingPaymentContext | null>;
  /**
   * After login/register: keep context only when same bound user.
   * Clears different-user and unbound/legacy contexts (no silent stamp).
   */
  bindToAuthenticatedUser: (userId: string) => Promise<PendingPaymentContext | null>;
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

function hasRecoverableIds(payload: PendingPaymentContext): boolean {
  return Boolean(
    payload.orderId ||
      payload.paymentTransactionId ||
      payload.merchantReference,
  );
}

export const pendingPaymentContextStorage: PendingPaymentContextStorage = {
  async save(context): Promise<void> {
    const payload: PendingPaymentContext = {
      userId: normalize(context.userId),
      orderId: normalize(context.orderId),
      paymentTransactionId: normalize(context.paymentTransactionId),
      merchantReference: normalize(context.merchantReference),
      successIndicator: normalize(context.successIndicator),
      resultIndicator: normalize(context.resultIndicator),
      checkoutSessionId: normalize(context.checkoutSessionId),
      updatedAt: context.updatedAt ?? new Date().toISOString(),
    };

    if (!hasRecoverableIds(payload)) {
      await SecureStore.deleteItemAsync(PENDING_PAYMENT_KEY);
      return;
    }

    await SecureStore.setItemAsync(PENDING_PAYMENT_KEY, JSON.stringify(payload));
  },

  async merge(partial): Promise<PendingPaymentContext | null> {
    const existing = await pendingPaymentContextStorage.read();
    await pendingPaymentContextStorage.save({
      userId:
        partial.userId !== undefined ? partial.userId : existing?.userId ?? null,
      orderId:
        partial.orderId !== undefined ? partial.orderId : existing?.orderId ?? null,
      paymentTransactionId:
        partial.paymentTransactionId !== undefined
          ? partial.paymentTransactionId
          : existing?.paymentTransactionId ?? null,
      merchantReference:
        partial.merchantReference !== undefined
          ? partial.merchantReference
          : existing?.merchantReference ?? null,
      successIndicator:
        partial.successIndicator !== undefined
          ? partial.successIndicator
          : existing?.successIndicator ?? null,
      resultIndicator:
        partial.resultIndicator !== undefined
          ? partial.resultIndicator
          : existing?.resultIndicator ?? null,
      checkoutSessionId:
        partial.checkoutSessionId !== undefined
          ? partial.checkoutSessionId
          : existing?.checkoutSessionId ?? null,
    });
    return pendingPaymentContextStorage.read();
  },

  async read(): Promise<PendingPaymentContext | null> {
    const raw = await SecureStore.getItemAsync(PENDING_PAYMENT_KEY);
    if (!raw?.trim()) return null;
    try {
      const parsed = JSON.parse(raw) as Record<string, unknown>;
      return {
        userId: normalize(typeof parsed.userId === 'string' ? parsed.userId : null),
        orderId: normalize(typeof parsed.orderId === 'string' ? parsed.orderId : null),
        paymentTransactionId: normalize(
          typeof parsed.paymentTransactionId === 'string'
            ? parsed.paymentTransactionId
            : null,
        ),
        merchantReference: normalize(
          typeof parsed.merchantReference === 'string'
            ? parsed.merchantReference
            : null,
        ),
        successIndicator: normalize(
          typeof parsed.successIndicator === 'string'
            ? parsed.successIndicator
            : null,
        ),
        resultIndicator: normalize(
          typeof parsed.resultIndicator === 'string'
            ? parsed.resultIndicator
            : null,
        ),
        checkoutSessionId: normalize(
          typeof parsed.checkoutSessionId === 'string'
            ? parsed.checkoutSessionId
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
    ttlMs = PAYMENT_RECOVERY_TTL_MS,
  ): Promise<PendingPaymentContext | null> {
    const context = await pendingPaymentContextStorage.read();
    if (!context) return null;
    if (isExpired(context.updatedAt, nowMs, ttlMs)) {
      await pendingPaymentContextStorage.clear();
      return null;
    }
    return context;
  },

  async bindToAuthenticatedUser(userId: string): Promise<PendingPaymentContext | null> {
    const normalizedUserId = normalize(userId);
    if (!normalizedUserId) {
      await pendingPaymentContextStorage.clear();
      return null;
    }

    const context = await pendingPaymentContextStorage.readValid();
    if (!context) return null;

    // Different owner — never let User B resume User A payment.
    if (context.userId && context.userId !== normalizedUserId) {
      await pendingPaymentContextStorage.clear();
      return null;
    }

    // Unbound / legacy — do not stamp onto the next login (prevents cross-account).
    // Cold-return still works via URL returnTo params + already-authenticated reconcile.
    if (!context.userId) {
      await pendingPaymentContextStorage.clear();
      return null;
    }

    return context;
  },

  async clear(): Promise<void> {
    await SecureStore.deleteItemAsync(PENDING_PAYMENT_KEY);
  },
};
