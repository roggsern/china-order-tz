"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { formatPrice } from "@/lib/catalog/utils";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CheckoutSessionApiError,
  applyPromotionToCheckoutSession,
  cancelCheckoutSession,
  parseSessionMoney,
  refreshCheckoutSession,
  startCheckoutSession,
  type CheckoutSessionPayload,
} from "@/lib/api/customer-checkout-session";
import {
  CustomerOrdersApiError,
  createOrderFromCheckoutSession,
} from "@/lib/api/customer-orders";
import { useCartActions } from "@/lib/cart/context";

type PanelState = "idle" | "loading" | "ready" | "error" | "expired";

interface CheckoutOrchestratorPanelProps {
  /** Restart when cart composition changes. */
  cartSignature: string;
  enabled?: boolean;
}

export function CheckoutOrchestratorPanel({
  cartSignature,
  enabled = true,
}: CheckoutOrchestratorPanelProps) {
  const router = useRouter();
  const { clearPurchasedItems } = useCartActions();
  const [state, setState] = useState<PanelState>("idle");
  const [session, setSession] = useState<CheckoutSessionPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<string[]>([]);
  const [isPlacingOrder, setIsPlacingOrder] = useState(false);
  const [couponCode, setCouponCode] = useState("");
  const [couponBusy, setCouponBusy] = useState(false);
  const [couponError, setCouponError] = useState<string | null>(null);

  const boot = useCallback(async () => {
    if (!enabled) {
      setState("idle");
      setSession(null);
      setError(null);
      setFieldErrors([]);
      return;
    }

    const token = getCustomerApiToken();
    if (!token) {
      setState("idle");
      setSession(null);
      setError(null);
      setFieldErrors([]);
      return;
    }

    setState("loading");
    setError(null);
    setFieldErrors([]);

    try {
      const next = await startCheckoutSession(token);
      setSession(next);
      setState(next.is_expired ? "expired" : "ready");
    } catch (err) {
      const apiError = err instanceof CheckoutSessionApiError ? err : null;
      const message =
        apiError?.message ?? "Unable to start checkout. Check your cart and try again.";
      const fields = apiError?.fieldErrors
        ? Object.values(apiError.fieldErrors).flat().filter(Boolean)
        : [];

      if (apiError?.statusCode === 401) {
        setState("idle");
        setSession(null);
        setError(null);
        return;
      }

      if (message.toLowerCase().includes("expired")) {
        setState("expired");
        setError(message);
        setFieldErrors(fields);
        setSession(null);
        return;
      }

      setState("error");
      setError(message);
      setFieldErrors(fields);
      setSession(null);
    }
  }, [enabled]);

  useEffect(() => {
    void boot();
  }, [boot, cartSignature]);

  const handleRefresh = async () => {
    const token = getCustomerApiToken();
    if (!token || !session?.id) {
      await boot();
      return;
    }

    setState("loading");
    try {
      const next = await refreshCheckoutSession(session.id, token);
      setSession(next);
      setError(null);
      setFieldErrors([]);
      setState(next.is_expired ? "expired" : "ready");
    } catch (err) {
      const apiError = err instanceof CheckoutSessionApiError ? err : null;
      const message = apiError?.message ?? "Unable to refresh checkout session.";
      if (message.toLowerCase().includes("expired")) {
        setState("expired");
        setError(message);
        setSession(null);
        return;
      }
      setState("error");
      setError(message);
    }
  };

  const handleRestart = async () => {
    const token = getCustomerApiToken();
    if (token && session?.id) {
      try {
        await cancelCheckoutSession(session.id, token);
      } catch {
        // Restart anyway.
      }
    }
    await boot();
  };

  const handleCreateOrder = async () => {
    const token = getCustomerApiToken();
    if (!token || !session?.id || isPlacingOrder) {
      return;
    }

    setIsPlacingOrder(true);
    setError(null);

    try {
      const order = await createOrderFromCheckoutSession(session.id, token);
      clearPurchasedItems();
      router.push(`/orders/${encodeURIComponent(order.order_number)}`);
    } catch (err) {
      const message =
        err instanceof CustomerOrdersApiError || err instanceof CheckoutSessionApiError
          ? err.message
          : "Unable to create order from this checkout session.";
      setError(message);
      setState("error");
      setIsPlacingOrder(false);
    }
  };

  if (!enabled || !getCustomerApiToken()) {
    return null;
  }

  const items = session?.cart?.items ?? [];
  const subtotal = parseSessionMoney(session?.subtotal);
  const shipping = parseSessionMoney(session?.shipping_total);
  const tax = parseSessionMoney(session?.tax_total);
  const discount = parseSessionMoney(session?.discount_total);
  const grand = parseSessionMoney(session?.grand_total);

  return (
    <section
      aria-label="Checkout session"
      className="rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5"
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.15em] text-[#8b6914]">
            Checkout Session
          </p>
          <p className="mt-1 text-sm text-zinc-500">
            Validated against cart, retail pricing, and inventory.
          </p>
        </div>
        {state === "ready" && session ? (
          <button
            type="button"
            onClick={() => void handleRefresh()}
            className="text-xs font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
          >
            Refresh
          </button>
        ) : null}
      </div>

      {state === "loading" ? (
        <p className="mt-4 text-sm text-zinc-500">Preparing checkout session…</p>
      ) : null}

      {state === "expired" ? (
        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3" role="alert">
          <p className="text-sm font-semibold text-amber-900">Session expired</p>
          <p className="mt-1 text-sm text-amber-800">
            {error ?? "Your checkout session expired. Start a new one to continue."}
          </p>
          <button
            type="button"
            onClick={() => void handleRestart()}
            className="mt-3 rounded-lg bg-amber-900 px-3 py-2 text-xs font-semibold text-white"
          >
            Start new session
          </button>
        </div>
      ) : null}

      {state === "error" ? (
        <div className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-3" role="alert">
          <p className="text-sm font-semibold text-red-900">Checkout validation failed</p>
          <p className="mt-1 text-sm text-red-800">{error}</p>
          {fieldErrors.length > 0 ? (
            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
              {fieldErrors.map((row) => (
                <li key={row}>{row}</li>
              ))}
            </ul>
          ) : null}
          <button
            type="button"
            onClick={() => void handleRestart()}
            className="mt-3 rounded-lg border border-red-300 bg-white px-3 py-2 text-xs font-semibold text-red-800"
          >
            Try again
          </button>
        </div>
      ) : null}

      {state === "ready" && session ? (
        <div className="mt-4 space-y-4">
          <ul className="divide-y divide-zinc-100 rounded-xl border border-zinc-100">
            {items.map((item) => {
              const unit = parseSessionMoney(item.price_snapshot ?? item.unit_price);
              const line = parseSessionMoney(item.subtotal) || unit * item.quantity;
              return (
                <li key={item.id} className="flex items-start justify-between gap-3 px-3 py-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-zinc-900">
                      {item.product?.name ?? "Product"}
                    </p>
                    <p className="mt-0.5 text-xs text-zinc-500">
                      {item.variant?.name || item.variant?.sku || "Variant"} · Qty {item.quantity}
                    </p>
                  </div>
                  <p className="shrink-0 text-sm font-semibold text-zinc-900">
                    {formatPrice(line)}
                  </p>
                </li>
              );
            })}
          </ul>

          <div className="rounded-xl border border-zinc-100 p-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-zinc-500">
              Coupon code
            </p>
            <div className="mt-2 flex gap-2">
              <input
                value={couponCode}
                onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                placeholder="Enter code"
                className="min-w-0 flex-1 rounded-lg border border-zinc-200 px-3 py-2 text-sm"
              />
              <button
                type="button"
                disabled={couponBusy || !couponCode.trim()}
                onClick={() => {
                  void (async () => {
                    if (!session) return;
                    setCouponBusy(true);
                    setCouponError(null);
                    try {
                      const next = await applyPromotionToCheckoutSession(
                        session.id,
                        couponCode,
                      );
                      setSession(next);
                      setCouponCode("");
                    } catch (err) {
                      setCouponError(
                        err instanceof CheckoutSessionApiError
                          ? err.message
                          : "Unable to apply coupon.",
                      );
                    } finally {
                      setCouponBusy(false);
                    }
                  })();
                }}
                className="rounded-lg bg-[#c9a227] px-3 py-2 text-xs font-semibold text-zinc-950 disabled:opacity-50"
              >
                {couponBusy ? "…" : "Apply"}
              </button>
            </div>
            {couponError ? (
              <p className="mt-2 text-xs text-red-600">{couponError}</p>
            ) : null}
            {session.applied_promotion_code || session.promotion?.code ? (
              <p className="mt-2 text-xs text-emerald-700">
                Applied: {session.promotion?.name ?? session.applied_promotion_code} (
                {session.applied_promotion_code ?? session.promotion?.code})
              </p>
            ) : null}
          </div>

          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-zinc-500">Subtotal</dt>
              <dd className="font-medium text-zinc-900">{formatPrice(subtotal)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-zinc-500">Shipping</dt>
              <dd className="font-medium text-zinc-900">{formatPrice(shipping)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-zinc-500">Tax</dt>
              <dd className="font-medium text-zinc-900">{formatPrice(tax)}</dd>
            </div>
            {discount > 0 ? (
              <div className="flex justify-between gap-3">
                <dt className="text-zinc-500">Discount</dt>
                <dd className="font-medium text-zinc-900">-{formatPrice(discount)}</dd>
              </div>
            ) : null}
            <div className="flex justify-between gap-3 border-t border-zinc-100 pt-2">
              <dt className="font-semibold text-zinc-900">Grand total</dt>
              <dd className="font-bold text-zinc-900">{formatPrice(grand)}</dd>
            </div>
          </dl>

          <p className="text-xs text-zinc-400">
            Status: {session.status}
            {session.expires_at
              ? ` · Expires ${new Date(session.expires_at).toLocaleString()}`
              : null}
          </p>

          <button
            type="button"
            onClick={() => void handleCreateOrder()}
            disabled={isPlacingOrder}
            className="flex w-full items-center justify-center rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {isPlacingOrder ? "Creating order…" : "Create order (no payment)"}
          </button>
          <p className="text-center text-[11px] text-zinc-400">
            Creates a permanent order snapshot. Payment is handled separately.
          </p>
        </div>
      ) : null}
    </section>
  );
}
