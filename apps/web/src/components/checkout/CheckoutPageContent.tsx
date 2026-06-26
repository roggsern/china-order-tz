"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCart } from "@/lib/cart/context";
import { calculateCartTotals } from "@/lib/cart/utils";
import { deepCopyCart, mapCartToOrderItems, buildShippingSnapshotFromCart } from "@/lib/checkout/cart-snapshot";
import { saveCheckoutDraft, getCheckoutDraft } from "@/lib/checkout/draft";
import type { CartTotals } from "@/lib/types/cart";
import type { OrderLineItem } from "@/lib/types/order";
import {
  EMPTY_CHECKOUT_FORM,
  type CheckoutFormData,
  type CheckoutFormErrors,
  type CustomerInformation,
  type ShippingAddress,
} from "@/lib/types/checkout";
import {
  hasCheckoutErrors,
  normalizeCheckoutForm,
  validateAddressLine1,
  validateCheckoutForm,
  validateCity,
  validateEmail,
  validateFirstName,
  validateLastName,
  validatePhone,
  validateRegion,
} from "@/lib/checkout/validation";
import { validateCartAgainstCatalog } from "@/lib/cart/validation";
import { productService } from "@/lib/services/product-service.client";
import { CheckoutSection } from "./CheckoutSection";
import { CustomerInformationForm } from "./CustomerInformationForm";
import { ShippingAddressForm } from "./ShippingAddressForm";
import { OrderNotesForm } from "./OrderNotesForm";
import { CheckoutOrderSummary } from "./CheckoutOrderSummary";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";

export function CheckoutPageContent() {
  const router = useRouter();
  const { items, savedForLater, discount, totals, isHydrated } = useCart();
  const [form, setForm] = useState<CheckoutFormData>(EMPTY_CHECKOUT_FORM);
  const [errors, setErrors] = useState<CheckoutFormErrors>({});
  const [submitError, setSubmitError] = useState<string | undefined>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const submitInFlightRef = useRef(false);
  const submitSnapshotRef = useRef<{ items: OrderLineItem[]; totals: CartTotals } | null>(null);

  const orderItems = useMemo(() => mapCartToOrderItems(items), [items]);
  const summaryItems = submitSnapshotRef.current?.items ?? orderItems;
  const summaryTotals = submitSnapshotRef.current?.totals ?? totals;

  useEffect(() => {
    if (!isHydrated || isSubmitting || submitInFlightRef.current) {
      return;
    }

    if (items.length === 0) {
      router.replace("/cart");
    }
  }, [isHydrated, isSubmitting, items.length, router]);

  const clearCustomerError = useCallback((field: keyof CustomerInformation) => {
    setErrors((prev) => {
      if (!prev.customer?.[field]) {
        return prev;
      }
      const nextCustomer = { ...prev.customer };
      delete nextCustomer[field];
      return {
        ...prev,
        customer: Object.keys(nextCustomer).length > 0 ? nextCustomer : undefined,
      };
    });
  }, []);

  const clearAddressError = useCallback((field: keyof ShippingAddress) => {
    setErrors((prev) => {
      if (!prev.shippingAddress?.[field]) {
        return prev;
      }
      const nextAddress = { ...prev.shippingAddress };
      delete nextAddress[field];
      return {
        ...prev,
        shippingAddress: Object.keys(nextAddress).length > 0 ? nextAddress : undefined,
      };
    });
  }, []);

  const handleCustomerBlur = useCallback(
    (field: keyof CustomerInformation) => {
      const value = form.customer[field];
      let message: string | undefined;

      if (field === "firstName") {
        message = validateFirstName(value);
      } else if (field === "lastName") {
        message = validateLastName(value);
      } else if (field === "email") {
        message = validateEmail(value);
      } else if (field === "phone") {
        message = validatePhone(value);
      }

      if (!message) {
        clearCustomerError(field);
        return;
      }

      setErrors((prev) => ({
        ...prev,
        customer: { ...prev.customer, [field]: message },
      }));
    },
    [clearCustomerError, form.customer],
  );

  const handleAddressBlur = useCallback(
    (field: keyof ShippingAddress) => {
      const value = form.shippingAddress[field];
      let message: string | undefined;

      if (field === "addressLine1") {
        message = validateAddressLine1(value);
      } else if (field === "city") {
        message = validateCity(value);
      } else if (field === "region") {
        message = validateRegion(value);
      }

      if (!message) {
        clearAddressError(field);
        return;
      }

      setErrors((prev) => ({
        ...prev,
        shippingAddress: { ...prev.shippingAddress, [field]: message },
      }));
    },
    [clearAddressError, form.shippingAddress],
  );

  const scrollToFirstError = () => {
    requestAnimationFrame(() => {
      document
        .querySelector('[aria-invalid="true"], [role="alert"]')
        ?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  };

  if (!isHydrated || (items.length === 0 && !isSubmitting && !submitInFlightRef.current)) {
    return (
      <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="h-8 w-56 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_400px]">
          <div className="h-96 animate-pulse rounded-3xl bg-zinc-50" />
          <div className="h-80 animate-pulse rounded-3xl bg-zinc-50" />
        </div>
      </div>
    );
  }

  const handleContinue = async () => {
    if (submitInFlightRef.current || isSubmitting) {
      return;
    }

    const normalized = normalizeCheckoutForm(form);
    setForm(normalized);

    const nextErrors = validateCheckoutForm(normalized);
    setErrors(nextErrors);

    if (hasCheckoutErrors(nextErrors)) {
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

      router.push("/checkout/payment");
    } catch {
      submitInFlightRef.current = false;
      setSubmitError("We couldn't continue to payment. Please try again.");
      setIsSubmitting(false);
    }
  };

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Secure checkout
          </p>
          <h1 className="mt-1.5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Checkout
          </h1>
          <p className="mt-2 max-w-xl text-sm leading-relaxed text-zinc-500">
            Enter your details and shipping address. You&apos;ll choose payment on the next step.
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

      <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start">
        <fieldset disabled={isSubmitting} className="space-y-6 border-0 p-0">
          <CheckoutSection
            title="Customer Information"
            description="We'll use this to send your order confirmation and delivery updates."
          >
            <CustomerInformationForm
              value={form.customer}
              errors={errors.customer}
              onChange={(customer) => setForm((prev) => ({ ...prev, customer }))}
              onBlurField={handleCustomerBlur}
              onClearError={clearCustomerError}
            />
          </CheckoutSection>

          <CheckoutSection
            title="Shipping Address"
            description="Where should we deliver your order in Tanzania?"
          >
            <ShippingAddressForm
              value={form.shippingAddress}
              errors={errors.shippingAddress}
              onChange={(shippingAddress) => setForm((prev) => ({ ...prev, shippingAddress }))}
              onBlurField={handleAddressBlur}
              onClearError={clearAddressError}
            />
          </CheckoutSection>

          <CheckoutSection
            title="Order Notes"
            description="Optional — add any special delivery instructions."
          >
            <OrderNotesForm
              value={form.orderNotes}
              onChange={(orderNotes) => setForm((prev) => ({ ...prev, orderNotes }))}
            />
          </CheckoutSection>

          {submitError ? (
            <p
              role="alert"
              className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
              {submitError}
            </p>
          ) : null}
        </fieldset>

        <CheckoutOrderSummary
          items={summaryItems}
          totals={summaryTotals}
          onSubmit={handleContinue}
          isSubmitting={isSubmitting}
          submitDisabled={isSubmitting}
          submitLabel={isSubmitting ? "Saving…" : "Continue to Payment"}
          submitHint="Shipping & totals — payment comes next"
          backHref="/cart"
          backLabel="← Back to cart"
        />
      </div>
    </div>
  );
}
