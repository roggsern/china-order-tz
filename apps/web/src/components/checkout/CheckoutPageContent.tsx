"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { useCart } from "@/lib/cart/context";
import { calculateCartTotals } from "@/lib/cart/utils";
import { deepCopyCart, mapCartToOrderItems, buildShippingSnapshotFromCart } from "@/lib/checkout/cart-snapshot";
import { saveCheckoutDraft, getCheckoutDraft } from "@/lib/checkout/draft";
import {
  getCheckoutWizardState,
  saveCheckoutWizardState,
  clearCheckoutWizardState,
} from "@/lib/checkout/wizard-state";
import type { CartTotals } from "@/lib/types/cart";
import type { OrderLineItem } from "@/lib/types/order";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import {
  EMPTY_CHECKOUT_FORM,
  type CheckoutFormData,
  type CheckoutFormErrors,
} from "@/lib/types/checkout";
import {
  hasCheckoutErrors,
  normalizeCheckoutForm,
  splitFullName,
  validateCheckoutStep1,
  validateCheckoutStep2,
} from "@/lib/checkout/validation";
import { validateCartAgainstCatalog } from "@/lib/cart/validation";
import { productService } from "@/lib/services/product-service.client";
import { CheckoutSection } from "./CheckoutSection";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";
import { CheckoutWizardProgress } from "./CheckoutWizardProgress";
import { CheckoutCustomerStep } from "./CheckoutCustomerStep";
import { CheckoutShippingStep } from "./CheckoutShippingStep";
import { CheckoutSummaryStep } from "./CheckoutSummaryStep";
import { Button } from "@/components/ui/Button";

type WizardStep = 1 | 2 | 3;

function getFullNameFromForm(form: CheckoutFormData): string {
  const combined = `${form.customer.firstName} ${form.customer.lastName}`.trim();
  return combined;
}

export function CheckoutPageContent() {
  const router = useRouter();
  const reduceMotion = useReducedMotion();
  const { items, savedForLater, discount, totals, isHydrated, updateShippingMethod } = useCart();

  const [step, setStep] = useState<WizardStep>(1);
  const [form, setForm] = useState<CheckoutFormData>(EMPTY_CHECKOUT_FORM);
  const [fullName, setFullName] = useState("");
  const [selectedShippingMethod, setSelectedShippingMethod] = useState<ShippingMethodCode | null>(
    null,
  );
  const [errors, setErrors] = useState<CheckoutFormErrors>({});
  const [shippingError, setShippingError] = useState<string | undefined>();
  const [submitError, setSubmitError] = useState<string | undefined>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [wizardLoaded, setWizardLoaded] = useState(false);

  const submitInFlightRef = useRef(false);
  const submitSnapshotRef = useRef<{ items: OrderLineItem[]; totals: CartTotals } | null>(null);

  const hasChinaItems = useMemo(() => items.some((item) => item.origin === "china"), [items]);

  useEffect(() => {
    if (!isHydrated || wizardLoaded) return;

    const savedWizard = getCheckoutWizardState();
    const savedDraft = getCheckoutDraft();

    if (savedWizard) {
      setStep(savedWizard.step);
      setForm(savedWizard.form);
      setFullName(getFullNameFromForm(savedWizard.form));
      setSelectedShippingMethod(savedWizard.selectedShippingMethod);
    } else if (savedDraft) {
      setForm({
        customer: savedDraft.customer,
        shippingAddress: savedDraft.shippingAddress,
        orderNotes: savedDraft.orderNotes,
      });
      setFullName(getFullNameFromForm({
        customer: savedDraft.customer,
        shippingAddress: savedDraft.shippingAddress,
        orderNotes: savedDraft.orderNotes,
      }));
      setSelectedShippingMethod(savedDraft.shippingMethod ?? null);
    }

    setWizardLoaded(true);
  }, [isHydrated, wizardLoaded]);

  useEffect(() => {
    if (!isHydrated || !wizardLoaded) return;

    saveCheckoutWizardState({
      step,
      form,
      selectedShippingMethod,
    });
  }, [step, form, selectedShippingMethod, isHydrated, wizardLoaded]);

  useEffect(() => {
    if (!isHydrated || isSubmitting || submitInFlightRef.current) return;

    if (items.length === 0) {
      router.replace("/cart");
    }
  }, [isHydrated, isSubmitting, items.length, router]);

  useEffect(() => {
    if (!hasChinaItems && selectedShippingMethod !== "local_delivery") {
      setSelectedShippingMethod("local_delivery");
    }
  }, [hasChinaItems, selectedShippingMethod]);

  const persistWizard = useCallback(
    (nextStep: WizardStep, nextForm: CheckoutFormData, nextMethod: ShippingMethodCode | null) => {
      saveCheckoutWizardState({
        step: nextStep,
        form: nextForm,
        selectedShippingMethod: nextMethod,
      });
    },
    [],
  );

  const applyShippingMethod = useCallback(
    (method: ShippingMethodCode) => {
      setSelectedShippingMethod(method);
      setShippingError(undefined);

      items.forEach((item) => {
        if (item.origin === "china") {
          updateShippingMethod(item.id, method);
        }
      });
    },
    [items, updateShippingMethod],
  );

  const clearFieldError = useCallback((scope: "customer" | "shippingAddress", field: string) => {
    setErrors((prev) => {
      if (scope === "customer") {
        if (!prev.customer?.[field as keyof CheckoutFormErrors["customer"]]) return prev;
        const nextCustomer = { ...prev.customer };
        delete nextCustomer[field as keyof typeof nextCustomer];
        return {
          ...prev,
          customer: Object.keys(nextCustomer).length > 0 ? nextCustomer : undefined,
        };
      }

      if (!prev.shippingAddress?.[field as keyof CheckoutFormErrors["shippingAddress"]]) {
        return prev;
      }
      const nextAddress = { ...prev.shippingAddress };
      delete nextAddress[field as keyof typeof nextAddress];
      return {
        ...prev,
        shippingAddress: Object.keys(nextAddress).length > 0 ? nextAddress : undefined,
      };
    });
  }, []);

  const scrollToFirstError = () => {
    requestAnimationFrame(() => {
      document
        .querySelector('[aria-invalid="true"], [role="alert"]')
        ?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  };

  const buildFormWithFullName = useCallback(
    (name: string, currentForm: CheckoutFormData): CheckoutFormData => {
      const { firstName, lastName } = splitFullName(name);
      return {
        ...currentForm,
        customer: {
          ...currentForm.customer,
          firstName,
          lastName,
        },
      };
    },
    [],
  );

  const handleNextFromStep1 = () => {
    const merged = buildFormWithFullName(fullName, form);
    const normalized = normalizeCheckoutForm(merged);
    setForm(normalized);
    setFullName(getFullNameFromForm(normalized));

    const nextErrors = validateCheckoutStep1(normalized);
    setErrors(nextErrors);

    if (hasCheckoutErrors(nextErrors)) {
      scrollToFirstError();
      return;
    }

    const nextStep: WizardStep = 2;
    setStep(nextStep);
    persistWizard(nextStep, normalized, selectedShippingMethod);
  };

  const handleNextFromStep2 = () => {
    const methodError = validateCheckoutStep2(hasChinaItems, selectedShippingMethod);
    if (methodError) {
      setShippingError(methodError);
      scrollToFirstError();
      return;
    }

    if (hasChinaItems && selectedShippingMethod) {
      applyShippingMethod(selectedShippingMethod);
    }

    const nextStep: WizardStep = 3;
    setStep(nextStep);
    persistWizard(nextStep, form, selectedShippingMethod);
  };

  const handleBack = () => {
    setSubmitError(undefined);
    setShippingError(undefined);
    const nextStep = (step - 1) as WizardStep;
    if (nextStep >= 1) {
      setStep(nextStep);
      persistWizard(nextStep, form, selectedShippingMethod);
    }
  };

  const handleContinueToPayment = async () => {
    if (submitInFlightRef.current || isSubmitting) return;

    const merged = buildFormWithFullName(fullName, form);
    const normalized = normalizeCheckoutForm(merged);
    setForm(normalized);

    const stepErrors = validateCheckoutStep1(normalized);
    const methodError = validateCheckoutStep2(hasChinaItems, selectedShippingMethod);

    if (hasCheckoutErrors(stepErrors) || methodError) {
      setErrors(stepErrors);
      setShippingError(methodError);
      if (hasCheckoutErrors(stepErrors)) setStep(1);
      else if (methodError) setStep(2);
      scrollToFirstError();
      return;
    }

    const cartBeforeOrder = deepCopyCart({ items, savedForLater, discount });

    if (cartBeforeOrder.items.length === 0) {
      setSubmitError("Your cart is empty. Add items before continuing.");
      return;
    }

    submitInFlightRef.current = true;
    setIsSubmitting(true);
    setSubmitError(undefined);

    try {
      const catalog = await productService.list();
      const validatedCart = validateCartAgainstCatalog(cartBeforeOrder, catalog);

      if (validatedCart.items.length === 0) {
        setSubmitError("Some items in your cart are no longer available. Please review your cart.");
        submitInFlightRef.current = false;
        setIsSubmitting(false);
        return;
      }

      const itemsForOrder = mapCartToOrderItems(validatedCart.items);
      const shippingSnapshot = buildShippingSnapshotFromCart(validatedCart.items);
      const totalsForOrder = calculateCartTotals(validatedCart);

      submitSnapshotRef.current = { items: itemsForOrder, totals: totalsForOrder };

      const existingDraft = getCheckoutDraft();

      saveCheckoutDraft({
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        orderNotes: normalized.orderNotes,
        cartSnapshot: validatedCart,
        items: itemsForOrder,
        totals: totalsForOrder,
        shippingMethod: shippingSnapshot.shippingMethod,
        itemShippingBreakdown: shippingSnapshot.itemShippingBreakdown,
        draftId: existingDraft?.draftId,
      });

      clearCheckoutWizardState();
      router.push("/checkout/payment");
    } catch {
      submitInFlightRef.current = false;
      setSubmitError("We couldn't continue to payment. Please try again.");
      setIsSubmitting(false);
    }
  };

  if (!isHydrated || !wizardLoaded || (items.length === 0 && !isSubmitting && !submitInFlightRef.current)) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-12 sm:px-6">
        <div className="h-8 w-56 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 h-96 animate-pulse rounded-3xl bg-zinc-50" />
      </div>
    );
  }

  const stepTitles: Record<WizardStep, { title: string; description: string }> = {
    1: {
      title: "Customer Information",
      description: "Tell us who you are and where to deliver your order.",
    },
    2: {
      title: "Shipping Method",
      description: "Choose the delivery speed that works best for you.",
    },
    3: {
      title: "Order Summary",
      description: "Review your items and totals before payment.",
    },
  };

  return (
    <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-10 lg:max-w-3xl lg:px-8">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Secure checkout
          </p>
          <h1 className="mt-1.5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Checkout
          </h1>
        </div>
        {!isSubmitting ? (
          <Link
            href="/cart"
            className="text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
          >
            Edit cart
          </Link>
        ) : null}
      </div>

      <CheckoutStepIndicator current="checkout" />
      <CheckoutWizardProgress currentStep={step} />

      <fieldset disabled={isSubmitting} className="mt-8 border-0 p-0">
        <CheckoutSection
          title={stepTitles[step].title}
          description={stepTitles[step].description}
        >
          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={step}
              initial={reduceMotion ? false : { opacity: 0, x: 16 }}
              animate={{ opacity: 1, x: 0 }}
              exit={reduceMotion ? undefined : { opacity: 0, x: -16 }}
              transition={{ duration: 0.25, ease: "easeOut" }}
            >
              {step === 1 && (
                <CheckoutCustomerStep
                  form={form}
                  errors={errors}
                  fullName={fullName}
                  onFullNameChange={setFullName}
                  onCustomerChange={(customer) => setForm((prev) => ({ ...prev, customer }))}
                  onAddressChange={(shippingAddress) =>
                    setForm((prev) => ({ ...prev, shippingAddress }))
                  }
                  onClearError={clearFieldError}
                />
              )}

              {step === 2 && (
                <CheckoutShippingStep
                  items={items}
                  selectedMethod={selectedShippingMethod}
                  onSelect={applyShippingMethod}
                  error={shippingError}
                />
              )}

              {step === 3 && (
                <CheckoutSummaryStep
                  items={items}
                  totals={totals}
                  form={form}
                  fullName={fullName || getFullNameFromForm(form)}
                />
              )}
            </motion.div>
          </AnimatePresence>
        </CheckoutSection>

        {submitError ? (
          <p
            role="alert"
            className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {submitError}
          </p>
        ) : null}

        <div className="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
          {step > 1 ? (
            <Button type="button" variant="secondary" size="lg" onClick={handleBack}>
              Back
            </Button>
          ) : (
            <Link
              href="/cart"
              className="inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-700 transition hover:border-zinc-300 hover:bg-zinc-50"
            >
              Back to cart
            </Link>
          )}

          {step === 1 && (
            <Button type="button" variant="primary" size="lg" onClick={handleNextFromStep1}>
              Next: Shipping
            </Button>
          )}

          {step === 2 && (
            <Button type="button" variant="primary" size="lg" onClick={handleNextFromStep2}>
              Next: Summary
            </Button>
          )}

          {step === 3 && (
            <Button
              type="button"
              variant="primary"
              size="lg"
              onClick={handleContinueToPayment}
              disabled={isSubmitting}
            >
              {isSubmitting ? "Saving…" : "Continue to Payment"}
            </Button>
          )}
        </div>
      </fieldset>
    </div>
  );
}
