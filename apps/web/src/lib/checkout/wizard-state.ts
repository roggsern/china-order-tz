import type { CheckoutFormData } from "@/lib/types/checkout";
import type { ShippingMethodCode } from "@/lib/shipping/types";

const CHECKOUT_WIZARD_KEY = "china-order-tz-checkout-wizard";

export type CheckoutWizardState = {
  step: 1 | 2 | 3;
  form: CheckoutFormData;
  selectedShippingMethod: ShippingMethodCode | null;
  savedAt: string;
};

export function saveCheckoutWizardState(
  state: Omit<CheckoutWizardState, "savedAt">,
): CheckoutWizardState {
  const payload: CheckoutWizardState = {
    ...state,
    savedAt: new Date().toISOString(),
  };

  if (typeof window !== "undefined") {
    window.sessionStorage.setItem(CHECKOUT_WIZARD_KEY, JSON.stringify(payload));
  }

  return payload;
}

export function getCheckoutWizardState(): CheckoutWizardState | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.sessionStorage.getItem(CHECKOUT_WIZARD_KEY);
    if (!raw) return null;

    const parsed = JSON.parse(raw) as CheckoutWizardState;
    if (!parsed.form || parsed.step < 1 || parsed.step > 3) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

export function clearCheckoutWizardState(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(CHECKOUT_WIZARD_KEY);
}
