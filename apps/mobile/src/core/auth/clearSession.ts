import { clearUserSensitiveQueryCaches } from '@/src/core/api/queryClientRegistry';
import { secureTokenStorage } from '@/src/core/storage';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { useCatalogUiStore } from '@/src/features/product/state/catalogUiStore';
import { pendingPaymentContextStorage } from '@/src/features/payments/storage/pendingPaymentContextStorage';
import { useAuthStore } from './authStore';
import { useJourneyStore } from './journeyStore';

export type ClearSessionOptions = {
  /** Clear cart/orders/checkout/payments caches (default true). */
  clearUserCaches?: boolean;
  /** Reset journey + TZ store selection (default false — preserve for commerce resume). */
  resetJourney?: boolean;
  /**
   * Clear pending NMB payment continuation context.
   * Default false — auth expiry must not erase reconciliation proof.
   * Explicit logout / confirmed paid must pass true.
   */
  clearPaymentContext?: boolean;
  /**
   * Clear unfinished checkout recovery context.
   * Default false — auth expiry must not erase checkout resume.
   * Explicit logout must pass true.
   */
  clearCheckoutContext?: boolean;
};

/**
 * Central auth teardown — always removes SecureStore token + Zustand user.
 *
 * Defaults preserve payment/checkout recovery contexts (auth-failure safe).
 * Use {@link clearSessionOnLogout} for destructive explicit logout.
 */
export async function clearSession(
  options: ClearSessionOptions = {},
): Promise<void> {
  const clearUserCaches = options.clearUserCaches !== false;
  const resetJourney = options.resetJourney === true;
  const clearPaymentContext = options.clearPaymentContext === true;
  const clearCheckoutContext = options.clearCheckoutContext === true;

  await secureTokenStorage.clearToken();
  useAuthStore.getState().setUnauthenticated();

  if (clearUserCaches) {
    clearUserSensitiveQueryCaches();
  }

  if (resetJourney) {
    useJourneyStore.getState().setJourney('CHINA_IMPORT');
    useCatalogUiStore.getState().setSelectedTzStoreSlug(null);
  }

  if (clearPaymentContext) {
    await pendingPaymentContextStorage.clear();
  }

  if (clearCheckoutContext) {
    await pendingCheckoutContextStorage.clear();
  }
}

/** Expired Sanctum / 401 / bootstrap unauthenticated — keep recovery contexts. */
export async function clearSessionOnAuthFailure(): Promise<void> {
  await clearSession({
    clearUserCaches: true,
    resetJourney: false,
    clearPaymentContext: false,
    clearCheckoutContext: false,
  });
}

/** Explicit user logout — strongest destructive clear. */
export async function clearSessionOnLogout(): Promise<void> {
  await clearSession({
    clearUserCaches: true,
    resetJourney: true,
    clearPaymentContext: true,
    clearCheckoutContext: true,
  });
}
