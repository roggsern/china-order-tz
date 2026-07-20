"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { motion, useReducedMotion } from "framer-motion";
import { useCart } from "@/lib/cart/context";
import { calculateCartTotals } from "@/lib/cart/utils";
import { deepCopyCart, mapCartToOrderItems, buildShippingSnapshotFromCart } from "@/lib/checkout/cart-snapshot";
import { saveCheckoutDraft, getCheckoutDraft } from "@/lib/checkout/draft";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerCheckoutApiError,
  mapBackendSummaryToTotals,
  runBackendCheckoutFlow,
} from "@/lib/api/customer-checkout";
import { saveLocalOrderFromBackendConfirmation } from "@/lib/checkout/backend-order";
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
} from "@/lib/checkout/validation";
import {
  type CheckoutShippingChoice,
  validateShippingChoice,
} from "@/lib/checkout/shipping-choice";
import { validateCartAgainstCatalog, summarizeCartValidationFailures } from "@/lib/cart/validation";
import { fetchClientCatalogProducts } from "@/lib/catalog/client-catalog";
import { productService } from "@/lib/services/product-service.client";
import { CheckoutSection } from "./CheckoutSection";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";
import { CheckoutCustomerStep } from "./CheckoutCustomerStep";
import { CheckoutShippingStep } from "./CheckoutShippingStep";
import { CheckoutSidebarSummary } from "./CheckoutSidebarSummary";
import { CheckoutMobileStickyBar } from "./CheckoutMobileStickyBar";
import { CheckoutEmptyState } from "./CheckoutEmptyState";
import { CheckoutOrchestratorPanel } from "./CheckoutOrchestratorPanel";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import {
  isAuthRequiredMessage,
  toFriendlyAuthMessage,
} from "@/lib/auth/friendly-auth-messages";
import { CheckoutPageSkeleton } from "@/components/ui/PageSkeletons";

function getFullNameFromForm(form: CheckoutFormData): string {
  return `${form.customer.firstName} ${form.customer.lastName}`.trim();
}

export function CheckoutPageContent() {
  const router = useRouter();
  const reduceMotion = useReducedMotion();
  const { items, savedForLater, discount, totals, isHydrated, updateShippingMethod } = useCart();

  const [form, setForm] = useState<CheckoutFormData>(EMPTY_CHECKOUT_FORM);
  const [fullName, setFullName] = useState("");
  const [shippingChoice, setShippingChoice] = useState<CheckoutShippingChoice | null>(null);
  const [selectedShippingMethod, setSelectedShippingMethod] = useState<ShippingMethodCode | null>(
    null,
  );
  const [errors, setErrors] = useState<CheckoutFormErrors>({});
  const [shippingError, setShippingError] = useState<string | undefined>();
  const [submitError, setSubmitError] = useState<string | undefined>();
  const [needsAuth, setNeedsAuth] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [wizardLoaded, setWizardLoaded] = useState(false);

  const submitInFlightRef = useRef(false);
  const submitSnapshotRef = useRef<{ items: OrderLineItem[]; totals: CartTotals } | null>(null);

  const hasChinaItems = useMemo(() => items.some((item) => item.origin === "china"), [items]);

  const checkoutCartSignature = useMemo(
    () =>
      items
        .map(
          (item) =>
            `${item.configurationId ?? item.id}:${item.quantity}:${item.unitPrice}`,
        )
        .join("|"),
    [items],
  );

  useEffect(() => {
    if (!isHydrated || wizardLoaded) return;

    const savedWizard = getCheckoutWizardState();
    const savedDraft = getCheckoutDraft();

    if (savedWizard) {
      setForm(savedWizard.form);
      setFullName(getFullNameFromForm(savedWizard.form));
      setShippingChoice(savedWizard.shippingChoice ?? null);
      setSelectedShippingMethod(savedWizard.selectedShippingMethod);
    } else if (savedDraft) {
      setForm({
        customer: savedDraft.customer,
        shippingAddress: savedDraft.shippingAddress,
        orderNotes: savedDraft.orderNotes,
      });
      setFullName(
        getFullNameFromForm({
          customer: savedDraft.customer,
          shippingAddress: savedDraft.shippingAddress,
          orderNotes: savedDraft.orderNotes,
        }),
      );
      setSelectedShippingMethod(savedDraft.shippingMethod ?? null);
    }

    setWizardLoaded(true);
  }, [isHydrated, wizardLoaded]);

  useEffect(() => {
    if (!isHydrated || !wizardLoaded) return;

    saveCheckoutWizardState({
      step: 1,
      form,
      shippingChoice,
      selectedShippingMethod,
    });
  }, [form, shippingChoice, selectedShippingMethod, isHydrated, wizardLoaded]);

  useEffect(() => {
    if (hasChinaItems) return;
    if (shippingChoice === "self_pickup" || shippingChoice === "negotiated_delivery") return;
    if (shippingChoice === "company_shipping" || shippingChoice === "customer_agent") {
      setShippingChoice(null);
      setSelectedShippingMethod(null);
    }
  }, [hasChinaItems, shippingChoice]);

  const applyShippingChoice = useCallback(
    (choice: CheckoutShippingChoice) => {
      setShippingChoice(choice);
      setShippingError(undefined);

      if (choice !== "company_shipping") {
        setSelectedShippingMethod(null);
      }
    },
    [],
  );

  const applyShippingMethod = useCallback(
    (method: ShippingMethodCode) => {
      setShippingChoice("company_shipping");
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

  const handleContinueToPayment = async () => {
    if (submitInFlightRef.current || isSubmitting) return;

    const merged = buildFormWithFullName(fullName, form);
    const normalized = normalizeCheckoutForm(merged);
    setForm(normalized);

    const stepErrors = validateCheckoutStep1(normalized);
    const methodError = validateShippingChoice(
      hasChinaItems,
      shippingChoice,
      selectedShippingMethod,
    );

    if (hasCheckoutErrors(stepErrors) || methodError) {
      setErrors(stepErrors);
      setShippingError(methodError);
      scrollToFirstError();
      return;
    }

    if (shippingChoice === "company_shipping" && selectedShippingMethod) {
      applyShippingMethod(selectedShippingMethod);
    }

    const cartBeforeOrder = deepCopyCart({ items, savedForLater, discount });

    if (cartBeforeOrder.items.length === 0) {
      setSubmitError("Your cart is empty. Add items before continuing.");
      return;
    }

    submitInFlightRef.current = true;
    setIsSubmitting(true);
    setSubmitError(undefined);
    setNeedsAuth(false);

    try {
      let catalog: Awaited<ReturnType<typeof fetchClientCatalogProducts>>;

      try {
        catalog = await fetchClientCatalogProducts();
      } catch (catalogError) {
        console.warn(
          "[checkout-validation] Live catalog unavailable, falling back to local product service.",
          catalogError,
        );
        catalog = await productService.list();
      }

      const validatedCart = validateCartAgainstCatalog(cartBeforeOrder, catalog);

      if (validatedCart.items.length === 0) {
        const failures = summarizeCartValidationFailures(cartBeforeOrder, catalog);
        console.error("[checkout-validation] All cart items rejected during validation.", failures);
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
      const draftId = existingDraft?.draftId ?? crypto.randomUUID();
      const apiToken = getCustomerApiToken();

      if (!apiToken) {
        setNeedsAuth(true);
        setSubmitError(undefined);
        submitInFlightRef.current = false;
        setIsSubmitting(false);
        requestAnimationFrame(() => {
          document
            .querySelector('[data-auth-invite="checkout"]')
            ?.scrollIntoView({ behavior: "smooth", block: "center" });
        });
        return;
      }

      const confirmation = await runBackendCheckoutFlow({
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        cart: validatedCart,
        token: apiToken,
        shippingChoice: shippingChoice!,
        shippingMethod:
          shippingChoice === "company_shipping" ? selectedShippingMethod : null,
      });

      const backendTotals = mapBackendSummaryToTotals(confirmation.summary, validatedCart.items);

      saveLocalOrderFromBackendConfirmation({
        confirmation,
        draftId,
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        orderNotes: normalized.orderNotes,
        items: itemsForOrder,
        totals: backendTotals,
        cartSnapshot: validatedCart,
        shippingMethod: shippingSnapshot.shippingMethod,
        itemShippingBreakdown: shippingSnapshot.itemShippingBreakdown,
      });

      saveCheckoutDraft({
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        orderNotes: normalized.orderNotes,
        cartSnapshot: validatedCart,
        items: itemsForOrder,
        totals: backendTotals,
        shippingMethod: shippingSnapshot.shippingMethod,
        itemShippingBreakdown: shippingSnapshot.itemShippingBreakdown,
        draftId,
        backendOrder: {
          id: confirmation.order.id,
          orderNumber: confirmation.order.order_number,
        },
        awaitingPayment: true,
      });

      clearCheckoutWizardState();
      router.push("/checkout/payment");
    } catch (error) {
      submitInFlightRef.current = false;
      if (error instanceof CustomerCheckoutApiError) {
        if (isAuthRequiredMessage(error.message) || error.statusCode === 401) {
          setNeedsAuth(true);
          setSubmitError(undefined);
        } else {
          setSubmitError(toFriendlyAuthMessage(error.message, error.message));
        }
      } else {
        setSubmitError("We couldn't continue to payment. Please try again.");
      }
      setIsSubmitting(false);
    }
  };

  if (!isHydrated || !wizardLoaded) {
    return <CheckoutPageSkeleton />;
  }

  if (items.length === 0 && !isSubmitting && !submitInFlightRef.current) {
    return <CheckoutEmptyState />;
  }

  const sectionMotion = reduceMotion
    ? {}
    : { initial: { opacity: 0, y: 12 }, animate: { opacity: 1, y: 0 } };

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:pb-10">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Secure checkout
          </p>
          <h1 className="mt-1.5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Checkout
          </h1>
          <p className="mt-2 max-w-xl text-sm leading-relaxed text-zinc-500">
            Confirm delivery, choose shipping, then continue to payment.
          </p>
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

      <div className="mt-8 grid gap-8 pb-28 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start lg:pb-0">
        <fieldset disabled={isSubmitting} className="space-y-6 border-0 p-0">
          <motion.div transition={{ duration: 0.28, ease: "easeOut" }} {...sectionMotion}>
            <CheckoutSection
              title="Delivery Address"
              description="Where should we deliver your order?"
            >
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
            </CheckoutSection>
          </motion.div>

          <motion.div
            transition={{ duration: 0.28, ease: "easeOut", delay: reduceMotion ? 0 : 0.05 }}
            {...sectionMotion}
          >
            <CheckoutSection
              title="Shipping Method"
              description="Choose shipping before payment. Totals include company freight only when you select CHINA ORDER TZ shipping."
            >
              <CheckoutShippingStep
                items={items}
                shippingChoice={shippingChoice}
                selectedMethod={selectedShippingMethod}
                onSelectChoice={applyShippingChoice}
                onSelectMethod={applyShippingMethod}
                error={shippingError}
              />
            </CheckoutSection>
          </motion.div>

          <motion.div
            transition={{ duration: 0.28, ease: "easeOut", delay: reduceMotion ? 0 : 0.1 }}
            {...sectionMotion}
          >
            <CheckoutSection
              title="Payment Method"
              description="You'll choose how to pay on the next step — M-Pesa, NMB, Selcom, bank transfer, or cash on delivery."
            >
              <div className="rounded-2xl border border-dashed border-[#c9a227]/35 bg-[#c9a227]/5 px-4 py-5">
                <p className="text-sm font-semibold text-zinc-900">Payment comes next</p>
                <p className="mt-1 text-sm leading-relaxed text-zinc-600">
                  After you confirm this order, you&apos;ll select a secure payment method. Your
                  cart and shipping details stay saved.
                </p>
              </div>
            </CheckoutSection>
          </motion.div>

          {needsAuth ? (
            <div data-auth-invite="checkout">
              <AuthInvitationCard context="checkout" returnUrl="/checkout" />
            </div>
          ) : null}

          {submitError ? (
            <p
              role="alert"
              className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
              {submitError}
            </p>
          ) : null}

          <Link
            href="/cart"
            className="inline-flex text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
          >
            ← Back to cart
          </Link>
        </fieldset>

        <div className="space-y-4 lg:sticky lg:top-24">
          <CheckoutOrchestratorPanel
            cartSignature={checkoutCartSignature}
            enabled={items.length > 0 && !isSubmitting}
          />
          <CheckoutSidebarSummary
            items={items}
            totals={totals}
            shippingMethod={
              shippingChoice === "company_shipping" ? selectedShippingMethod : null
            }
            onSubmit={handleContinueToPayment}
            isSubmitting={isSubmitting}
            submitDisabled={!shippingChoice || (shippingChoice === "company_shipping" && !selectedShippingMethod)}
            submitLabel="Continue to Payment"
            submitHint="Secure checkout — shipping must be selected before payment"
            mode="cart"
            className="lg:static"
          />
        </div>
      </div>

      <CheckoutMobileStickyBar
        totals={totals}
        onSubmit={handleContinueToPayment}
        isSubmitting={isSubmitting}
        submitDisabled={!shippingChoice || (shippingChoice === "company_shipping" && !selectedShippingMethod)}
        submitLabel="Continue to Payment"
        itemCount={items.length}
      />
    </div>
  );
}
