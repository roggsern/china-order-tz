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
import { prefetchCheckoutPaymentMethods } from "@/lib/api/checkout-payment-methods";
import {
  CustomerCheckoutApiError,
  mapBackendSummaryToTotals,
  runBackendCheckoutFlow,
} from "@/lib/api/customer-checkout";
import { loadVisitorIdentity } from "@/lib/storefront/visitor-identity";
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
} from "@/lib/types/checkout";
import {
  fetchCustomerAddresses,
  createCustomerAddress,
  setDefaultCustomerAddress,
  type CustomerAddress,
  type CustomerAddressInput,
} from "@/lib/api/customer-addresses";
import { fetchCustomerProfile } from "@/lib/api/customer-profile";
import {
  applyCustomerAddressToCheckoutForm,
  CHECKOUT_DELIVERY_ADDRESS_REQUIRED,
  isCheckoutDeliveryAddressReady,
  mergeProfileIntoCheckoutCustomer,
  resolveInitialCheckoutAddressSelection,
} from "@/lib/checkout/address-book";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import {
  hasCheckoutErrors,
  normalizeCheckoutForm,
  validateCheckoutStep1,
} from "@/lib/checkout/validation";
import {
  type CheckoutShippingChoice,
  EMPTY_CUSTOMER_AGENT_DETAILS,
  validateCustomerAgentDetails,
  validateShippingChoice,
} from "@/lib/checkout/shipping-choice";
import { resolveCheckoutDisplayTotals, shouldShowCompanyShippingEstimate } from "@/lib/checkout/display-totals";
import { validateCartAgainstCatalog, summarizeCartValidationFailures } from "@/lib/cart/validation";
import { hasBlockingCartSyncError } from "@/lib/cart/sync-errors";
import { fetchClientCatalogProducts, fetchClientCatalogProductsForSlugs } from "@/lib/catalog/client-catalog";
import { productService } from "@/lib/services/product-service.client";
import { CartSyncErrorAlert } from "@/components/cart/CartSyncErrorAlert";
import { CheckoutSection } from "./CheckoutSection";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";
import { CheckoutShippingStep } from "./CheckoutShippingStep";
import { CheckoutAddressStep } from "./CheckoutAddressStep";
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
import { markCheckoutPendingAuth } from "@/lib/checkout/auth-resume";
import { useStorefrontTracking } from "@/components/storefront/StorefrontTrackingProvider";
import { fetchShippingDurations } from "@/lib/shipping/durations";

function buildAgentOrderNotes(
  baseNotes: string,
  agentDetails: typeof EMPTY_CUSTOMER_AGENT_DETAILS,
): string {
  const address = agentDetails.address.trim();
  if (!address) {
    return baseNotes;
  }

  const agentNote = `Shipping agent address: ${address}`;
  return baseNotes.trim() ? `${baseNotes.trim()}\n${agentNote}` : agentNote;
}

export function CheckoutPageContent() {
  const router = useRouter();
  const reduceMotion = useReducedMotion();
  const { trackCheckoutStarted } = useStorefrontTracking();
  const checkoutTrackedRef = useRef(false);
  const { items, savedForLater, discount, totals, isHydrated, syncError, clearSyncError, updateShippingMethod } =
    useCart();

  const { session: customerSession, isLoggedIn, isReady: sessionReady } = useCustomerSession();
  const [form, setForm] = useState<CheckoutFormData>(EMPTY_CHECKOUT_FORM);
  const [loadedProfile, setLoadedProfile] = useState<Awaited<
    ReturnType<typeof fetchCustomerProfile>
  >>(null);
  const [savedAddresses, setSavedAddresses] = useState<CustomerAddress[]>([]);
  const [savedAddressesDefaultId, setSavedAddressesDefaultId] = useState<string | null>(null);
  const [selectedAddressId, setSelectedAddressId] = useState<string | null>(null);
  const [addressesLoading, setAddressesLoading] = useState(false);
  const [addressSaving, setAddressSaving] = useState(false);
  const [showAddAddressForm, setShowAddAddressForm] = useState(false);
  const [addressBookReady, setAddressBookReady] = useState(false);
  const [addressError, setAddressError] = useState<string | undefined>();
  const [shippingChoice, setShippingChoice] = useState<CheckoutShippingChoice | null>(null);
  const [customerAgentDetails, setCustomerAgentDetails] = useState(EMPTY_CUSTOMER_AGENT_DETAILS);
  const [selectedShippingMethod, setSelectedShippingMethod] = useState<ShippingMethodCode | null>(
    null,
  );
  const [shippingError, setShippingError] = useState<string | undefined>();
  const [agentError, setAgentError] = useState<string | undefined>();
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
    void fetchShippingDurations();
  }, []);

  useEffect(() => {
    if (!sessionReady || !isLoggedIn) {
      return;
    }
    prefetchCheckoutPaymentMethods();
  }, [sessionReady, isLoggedIn]);

  useEffect(() => {
    if (!sessionReady || !isLoggedIn || !shippingChoice) {
      return;
    }
    // Warm again after shipping is chosen — still on checkout, before Continue.
    prefetchCheckoutPaymentMethods();
  }, [sessionReady, isLoggedIn, shippingChoice]);

  useEffect(() => {
    if (checkoutTrackedRef.current) {
      return;
    }

    checkoutTrackedRef.current = true;
    void trackCheckoutStarted();
  }, [trackCheckoutStarted]);

  useEffect(() => {
    if (!isHydrated || wizardLoaded) return;

    // Wait for local session hydration when authenticated so identity is available
    // before address mapping runs (address must never win the identity race).
    const token = getCustomerApiToken();
    if (token && !sessionReady) {
      return;
    }

    const savedWizard = getCheckoutWizardState();
    const savedDraft = getCheckoutDraft();

    let nextForm: CheckoutFormData = EMPTY_CHECKOUT_FORM;
    let restoredAddressId: string | null = null;

    if (savedWizard) {
      nextForm = savedWizard.form;
      restoredAddressId = savedWizard.selectedAddressId ?? null;
      setShippingChoice(savedWizard.shippingChoice ?? null);
      setSelectedShippingMethod(savedWizard.selectedShippingMethod);
      setCustomerAgentDetails(savedWizard.customerAgentDetails ?? EMPTY_CUSTOMER_AGENT_DETAILS);
    } else if (savedDraft) {
      nextForm = {
        customer: savedDraft.customer,
        shippingAddress: savedDraft.shippingAddress,
        orderNotes: savedDraft.orderNotes,
      };
      setSelectedShippingMethod(savedDraft.shippingMethod ?? null);
    }

    // Seed identity from session before address book loads.
    nextForm = {
      ...nextForm,
      customer: mergeProfileIntoCheckoutCustomer(nextForm.customer, null, customerSession),
    };

    setForm(nextForm);
    setWizardLoaded(true);

    if (!token) {
      setAddressBookReady(true);
      return;
    }

    setAddressesLoading(true);
    void (async () => {
      try {
        const [profileResult, addressResult] = await Promise.all([
          fetchCustomerProfile().catch(() => null),
          fetchCustomerAddresses(),
        ]);

        setLoadedProfile(profileResult);

        setForm((current) => ({
          ...current,
          customer: mergeProfileIntoCheckoutCustomer(
            current.customer,
            profileResult,
            customerSession,
          ),
        }));

        const { addresses, defaultId } = addressResult;
        setSavedAddresses(addresses);
        setSavedAddressesDefaultId(defaultId);

        const initialId = resolveInitialCheckoutAddressSelection(
          addresses,
          defaultId,
          restoredAddressId,
        );
        setSelectedAddressId(initialId);
        setShowAddAddressForm(addresses.length === 0);

        if (initialId) {
          const picked = addresses.find((row) => row.id === initialId);
          if (picked) {
            setForm((current) => applyCustomerAddressToCheckoutForm(current, picked));
          }
        }
      } catch {
        setAddressError("Unable to load saved addresses. You can add one below.");
        setShowAddAddressForm(true);
      } finally {
        setAddressesLoading(false);
        setAddressBookReady(true);
      }
    })();
  }, [isHydrated, wizardLoaded, customerSession, sessionReady]);

  // Late session/profile identity merge — fills empty identity only; never uses recipient.
  useEffect(() => {
    if (!wizardLoaded || !sessionReady) {
      return;
    }

    setForm((current) => ({
      ...current,
      customer: mergeProfileIntoCheckoutCustomer(
        current.customer,
        loadedProfile,
        customerSession,
      ),
    }));
  }, [customerSession, loadedProfile, sessionReady, wizardLoaded]);

  useEffect(() => {
    if (!isHydrated || !wizardLoaded) return;

    saveCheckoutWizardState({
      step: 1,
      form,
      shippingChoice,
      selectedShippingMethod,
      selectedAddressId,
      customerAgentDetails,
    });
  }, [form, shippingChoice, selectedShippingMethod, selectedAddressId, customerAgentDetails, isHydrated, wizardLoaded]);

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
      setAgentError(undefined);

      if (choice !== "company_shipping") {
        setSelectedShippingMethod(null);
      }

      if (choice !== "customer_agent") {
        setCustomerAgentDetails(EMPTY_CUSTOMER_AGENT_DETAILS);
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

  const handleSelectSavedAddress = useCallback(
    (addressId: string) => {
      const picked = savedAddresses.find((row) => row.id === addressId);
      if (!picked) {
        return;
      }

      setSelectedAddressId(addressId);
      setAddressError(undefined);
      setForm((current) => applyCustomerAddressToCheckoutForm(current, picked));
    },
    [savedAddresses],
  );

  const handleSaveCheckoutAddress = useCallback(
    async (input: CustomerAddressInput) => {
      setAddressSaving(true);
      try {
        const created = await createCustomerAddress({ ...input, is_default: true });
        setSavedAddresses((prev) => {
          const withoutNew = prev.filter((row) => row.id !== created.id);
          const demoted = withoutNew.map((row) =>
            created.is_default ? { ...row, is_default: false } : row,
          );
          return [created, ...demoted];
        });
        setSavedAddressesDefaultId(created.id);
        setSelectedAddressId(created.id);
        setAddressError(undefined);
        setForm((current) => applyCustomerAddressToCheckoutForm(current, created));
      } finally {
        setAddressSaving(false);
      }
    },
    [],
  );

  const deliveryAddressReady =
    !isLoggedIn || isCheckoutDeliveryAddressReady(selectedAddressId, savedAddresses);

  const profileContactLabel = useMemo(() => {
    const name = `${form.customer.firstName} ${form.customer.lastName}`.trim();
    const parts = [name, form.customer.phone.trim(), form.customer.email.trim()].filter(Boolean);
    return parts.join(" · ");
  }, [form.customer]);

  const displayTotals = useMemo(
    () => resolveCheckoutDisplayTotals(totals, shippingChoice, selectedShippingMethod),
    [totals, shippingChoice, selectedShippingMethod],
  );

  const scrollToFirstError = () => {
    requestAnimationFrame(() => {
      document
        .querySelector('[aria-invalid="true"], [role="alert"]')
        ?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  };

  const hasBlockingSyncError = hasBlockingCartSyncError(syncError);

  const checkoutBlocked =
    hasBlockingSyncError ||
    (isLoggedIn && !deliveryAddressReady) ||
    !shippingChoice ||
    (shippingChoice === "company_shipping" && !selectedShippingMethod) ||
    (shippingChoice === "customer_agent" &&
      Boolean(validateCustomerAgentDetails(customerAgentDetails)));

  const handleContinueToPayment = async () => {
    if (submitInFlightRef.current || isSubmitting) return;

    if (hasBlockingSyncError) {
      scrollToFirstError();
      return;
    }

    const normalized = normalizeCheckoutForm(form);
    setForm(normalized);

    const stepErrors = validateCheckoutStep1(normalized);
    const methodError = validateShippingChoice(
      hasChinaItems,
      shippingChoice,
      selectedShippingMethod,
    );
    const nextAgentError =
      shippingChoice === "customer_agent"
        ? validateCustomerAgentDetails(customerAgentDetails)
        : undefined;

    if (hasCheckoutErrors(stepErrors) || methodError || nextAgentError) {
      setShippingError(methodError);
      setAgentError(nextAgentError);
      scrollToFirstError();
      return;
    }

    if (isLoggedIn && !isCheckoutDeliveryAddressReady(selectedAddressId, savedAddresses)) {
      setAddressError(CHECKOUT_DELIVERY_ADDRESS_REQUIRED);
      scrollToFirstError();
      return;
    }

    setAddressError(undefined);
    setAgentError(undefined);

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
        const slugs = cartBeforeOrder.items.map((item) => item.slug);
        catalog = await fetchClientCatalogProductsForSlugs(slugs);
        if (catalog.length === 0) {
          catalog = await fetchClientCatalogProducts();
        }
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
        markCheckoutPendingAuth();
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

      const selectedAddress =
        savedAddresses.find((row) => row.id === selectedAddressId) ?? null;

      // Always sync the selected address book row into delivery_addresses so the
      // order shipping snapshot uses recipient_name (not account identity).
      if (selectedAddress) {
        await setDefaultCustomerAddress(selectedAddress.id);
        setSavedAddresses((prev) =>
          prev.map((row) => ({
            ...row,
            is_default: row.id === selectedAddress.id,
          })),
        );
        setSavedAddressesDefaultId(selectedAddress.id);
      }

      const confirmation = await runBackendCheckoutFlow({
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        cart: validatedCart,
        token: apiToken,
        shippingChoice: shippingChoice!,
        shippingMethod:
          shippingChoice === "company_shipping" ? selectedShippingMethod : null,
        agentName: customerAgentDetails.name.trim() || null,
        agentContact: customerAgentDetails.phone.trim() || null,
      });

      const orderNotes = buildAgentOrderNotes(normalized.orderNotes, customerAgentDetails);

      const backendTotals = mapBackendSummaryToTotals(confirmation.summary, validatedCart.items);

      saveLocalOrderFromBackendConfirmation({
        confirmation,
        draftId,
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        orderNotes,
        items: itemsForOrder,
        totals: backendTotals,
        cartSnapshot: validatedCart,
        shippingMethod: shippingSnapshot.shippingMethod,
        itemShippingBreakdown: shippingSnapshot.itemShippingBreakdown,
      });

      saveCheckoutDraft({
        customer: normalized.customer,
        shippingAddress: normalized.shippingAddress,
        orderNotes,
        cartSnapshot: validatedCart,
        items: itemsForOrder,
        totals: backendTotals,
        shippingChoice: shippingChoice!,
        shippingMethod:
          shippingChoice === "company_shipping" ? selectedShippingMethod : null,
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
        } else if (/delivery address/i.test(error.message)) {
          setAddressError(CHECKOUT_DELIVERY_ADDRESS_REQUIRED);
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

  if (!isHydrated || !wizardLoaded || !addressBookReady) {
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
            {hasChinaItems
              ? "Choose shipping, then continue to secure NMB payment."
              : "Choose your collection preference, then continue to secure NMB payment."}
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

      {syncError ? (
        <CartSyncErrorAlert
          message={syncError}
          onDismiss={clearSyncError}
          className="mt-6"
        />
      ) : null}

      <div className="mt-8 grid gap-8 pb-28 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start lg:pb-0">
        <fieldset disabled={isSubmitting} className="space-y-6 border-0 p-0">
          <motion.div transition={{ duration: 0.28, ease: "easeOut" }} {...sectionMotion}>
            <CheckoutSection
              title="Delivery Address"
              description="Choose a saved address or add one to continue. Your contact details come from your account."
            >
              {isLoggedIn && profileContactLabel ? (
                <div className="mb-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                  <p className="font-semibold text-zinc-900">Delivering to</p>
                  <p className="mt-1">{profileContactLabel}</p>
                </div>
              ) : null}

              {isLoggedIn ? (
                <CheckoutAddressStep
                  addresses={savedAddresses}
                  selectedAddressId={selectedAddressId}
                  onSelectAddress={handleSelectSavedAddress}
                  showAddForm={showAddAddressForm}
                  onShowAddForm={setShowAddAddressForm}
                  onSaveNewAddress={handleSaveCheckoutAddress}
                  isLoading={addressesLoading}
                  isSaving={addressSaving}
                  error={addressError}
                  profileDefaults={{
                    recipient_name: `${form.customer.firstName} ${form.customer.lastName}`.trim(),
                    phone: form.customer.phone,
                  }}
                />
              ) : (
                <p className="text-sm text-zinc-600">
                  Sign in to use saved addresses and continue to payment.
                </p>
              )}
            </CheckoutSection>
          </motion.div>

          <motion.div transition={{ duration: 0.28, ease: "easeOut" }} {...sectionMotion}>
            <CheckoutSection
              title={hasChinaItems ? "Shipping Method" : "Collection Preference"}
              description={
                hasChinaItems
                  ? "Choose shipping before payment. CHINA ORDER TZ freight applies only when you select company shipping."
                  : "Choose how you would like to receive your order. No freight calculation or courier selection."
              }
            >
              <CheckoutShippingStep
                items={items}
                shippingChoice={shippingChoice}
                selectedMethod={selectedShippingMethod}
                customerAgentDetails={customerAgentDetails}
                agentError={agentError}
                onSelectChoice={applyShippingChoice}
                onSelectMethod={applyShippingMethod}
                onAgentDetailsChange={(details) => {
                  setCustomerAgentDetails(details);
                  setAgentError(undefined);
                }}
                error={shippingError}
              />
            </CheckoutSection>
          </motion.div>

          <motion.div
            transition={{ duration: 0.28, ease: "easeOut", delay: reduceMotion ? 0 : 0.05 }}
            {...sectionMotion}
          >
            <CheckoutSection
              title="Secure Payment"
              description="Payment is completed securely through NMB on the next step."
            >
              <div className="rounded-2xl border border-dashed border-[#c9a227]/35 bg-[#c9a227]/5 px-4 py-5">
                <p className="text-sm font-semibold text-zinc-900">Pay via Bank Cards or Mobile Money</p>
                <p className="mt-1 text-sm leading-relaxed text-zinc-600">
                  Secure checkout powered by NMB. Your shipping choice stays saved when you continue.
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
            totals={displayTotals}
            shippingMethod={
              shippingChoice === "company_shipping" ? selectedShippingMethod : null
            }
            showShippingEstimate={shouldShowCompanyShippingEstimate(
              shippingChoice,
              selectedShippingMethod,
            )}
            onSubmit={handleContinueToPayment}
            isSubmitting={isSubmitting}
            submitDisabled={checkoutBlocked}
            submitLabel="Continue to Payment"
            submitHint={
              hasBlockingSyncError
                ? "Resolve the cart issue above before continuing to payment"
                : isLoggedIn && !deliveryAddressReady
                  ? CHECKOUT_DELIVERY_ADDRESS_REQUIRED
                  : "Secure checkout — address and shipping must be selected before payment"
            }
            mode="cart"
            className="lg:static"
          />
        </div>
      </div>

      <CheckoutMobileStickyBar
        totals={displayTotals}
        onSubmit={handleContinueToPayment}
        isSubmitting={isSubmitting}
        submitDisabled={checkoutBlocked}
        submitLabel="Continue to Payment"
        itemCount={items.length}
      />
    </div>
  );
}
