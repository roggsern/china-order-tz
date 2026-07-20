"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  closePosSession,
  completePosSale,
  fetchPosPaymentMethods,
  fetchPosSession,
  fetchPosStores,
  openPosSession,
  printPosReceipt,
  quotePosSale,
  searchPosCatalog,
  searchPosCustomers,
  type PosCartLine,
  type PosCatalogItem,
  type PosPaymentMethod,
  type PosReceipt,
  type PosSession,
  type PosSessionSummary,
  type PosStore,
} from "@/lib/api/admin-pos";
import {
  fetchLoyaltyRewards,
  lookupPosLoyalty,
  redeemPosLoyalty,
} from "@/lib/api/admin-loyalty";

function money(value: string | number | null | undefined): string {
  const n = Number(value ?? 0);
  return `${n.toLocaleString("en-TZ", { minimumFractionDigits: 0, maximumFractionDigits: 0 })} TZS`;
}

function lineTotal(unit: string, qty: number): string {
  return (Number(unit) * qty).toFixed(2);
}

export function PosCashierPanel() {
  const [stores, setStores] = useState<PosStore[]>([]);
  const [session, setSession] = useState<PosSession | null>(null);
  const [summary, setSummary] = useState<PosSessionSummary | null>(null);
  const [selectedStoreId, setSelectedStoreId] = useState("");
  const [openingFloat, setOpeningFloat] = useState("100000");
  const [closingCash, setClosingCash] = useState("");
  const [varianceReason, setVarianceReason] = useState("cash_counting_error");
  const [closingNotes, setClosingNotes] = useState("");
  const [showCloseForm, setShowCloseForm] = useState(false);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<PosCatalogItem[]>([]);
  const [cart, setCart] = useState<PosCartLine[]>([]);
  const [methods, setMethods] = useState<PosPaymentMethod[]>([]);
  const [paymentCode, setPaymentCode] = useState("CASH");
  const [amountReceived, setAmountReceived] = useState("");
  const [promotionCode, setPromotionCode] = useState("");
  const [customerQuery, setCustomerQuery] = useState("");
  const [customers, setCustomers] = useState<Array<{ id: string; name: string; email: string }>>([]);
  const [customer, setCustomer] = useState<{ id: string; name: string; email: string } | null>(null);
  const [loyalty, setLoyalty] = useState<{
    id: string;
    loyalty_number: string;
    points_balance: number;
    tier?: string | null;
  } | null>(null);
  const [loyaltyRewards, setLoyaltyRewards] = useState<
    Array<{ id: string; name: string; points_cost: number }>
  >([]);
  const [quote, setQuote] = useState<{
    subtotal: string;
    discount_total: string;
    grand_total: string;
    promotion: { promotion_name?: string; discount_amount?: string } | null;
  } | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [lastSale, setLastSale] = useState<string | null>(null);
  const [lastReceipt, setLastReceipt] = useState<PosReceipt | null>(null);

  const selectedStore = useMemo(
    () => stores.find((s) => s.id === (session?.store_id || selectedStoreId)),
    [stores, session, selectedStoreId],
  );

  const refresh = useCallback(async () => {
    const [storeList, current, payMethods] = await Promise.all([
      fetchPosStores(),
      fetchPosSession(),
      fetchPosPaymentMethods(),
    ]);
    setStores(storeList);
    setSession(current);
    setSummary(current?.summary ?? null);
    if (payMethods[0] && !paymentCode) {
      setPaymentCode(payMethods[0].code);
    }
    if (!selectedStoreId && storeList[0]) {
      setSelectedStoreId(storeList[0].id);
    }
    if (current?.store_id) {
      setSelectedStoreId(current.store_id);
    }
  }, [paymentCode, selectedStoreId]);

  useEffect(() => {
    refresh().catch((e: Error) => setError(e.message));
  }, [refresh]);

  useEffect(() => {
    if (!session) {
      setResults([]);
      return;
    }
    const handle = setTimeout(() => {
      searchPosCatalog(query)
        .then(setResults)
        .catch((e: Error) => setError(e.message));
    }, 250);
    return () => clearTimeout(handle);
  }, [query, session]);

  useEffect(() => {
    if (cart.length === 0) {
      setQuote(null);
      return;
    }
    const handle = setTimeout(() => {
      quotePosSale({
        items: cart.map((line) => ({
          product_id: line.product_id,
          product_variant_id: line.product_variant_id,
          quantity: line.quantity,
        })),
        customer_id: customer?.id,
        promotion_code: promotionCode || undefined,
      })
        .then((res) => setQuote(res.data))
        .catch(() => setQuote(null));
    }, 300);
    return () => clearTimeout(handle);
  }, [cart, customer, promotionCode]);

  const addToCart = (item: PosCatalogItem) => {
    if (!item.in_stock) return;
    setCart((prev) => {
      const existing = prev.find((l) => l.product_variant_id === item.product_variant_id);
      if (existing) {
        const quantity = Math.min(existing.quantity + 1, item.available_stock);
        return prev.map((l) =>
          l.product_variant_id === item.product_variant_id
            ? { ...l, quantity, line_total: lineTotal(l.unit_price, quantity) }
            : l,
        );
      }
      return [{ ...item, quantity: 1, line_total: lineTotal(item.unit_price, 1) }];
    });
  };

  const updateQty = (variantId: string, quantity: number) => {
    setCart((prev) =>
      prev
        .map((l) => {
          if (l.product_variant_id !== variantId) return l;
          const qty = Math.max(0, Math.min(quantity, l.available_stock));
          return { ...l, quantity: qty, line_total: lineTotal(l.unit_price, qty) };
        })
        .filter((l) => l.quantity > 0),
    );
  };

  const openSession = async () => {
    setBusy(true);
    setError(null);
    try {
      const store = stores.find((s) => s.id === selectedStoreId) ?? stores[0];
      if (!store) throw new Error("No assigned store available.");
      const terminal = store.terminals?.find((t) => t.is_active) ?? store.terminals?.[0];
      if (!terminal) throw new Error("Store has no POS terminal.");
      const float = Number(openingFloat);
      if (Number.isNaN(float) || float < 0) throw new Error("Opening float is required.");
      const opened = await openPosSession({
        store_id: store.id,
        terminal_id: terminal.id,
        opening_float: float,
      });
      setSession(opened);
      setSummary(opened.summary ?? null);
      setMessage(`Session open — ${store.name} · float ${money(float)}`);
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to open session.");
    } finally {
      setBusy(false);
    }
  };

  const closeSession = async () => {
    setBusy(true);
    setError(null);
    try {
      const actual = Number(closingCash);
      if (Number.isNaN(actual) || actual < 0) {
        throw new Error("Enter the actual cash count.");
      }
      const expected = Number(summary?.expected_cash ?? 0);
      const body: {
        closing_cash: number;
        variance_reason?: string;
        closing_notes?: string;
      } = {
        closing_cash: actual,
        closing_notes: closingNotes || undefined,
      };
      if (actual !== expected) {
        body.variance_reason = varianceReason;
      }
      const closed = await closePosSession(body);
      const variance = closed.summary?.variance_amount ?? closed.variance_amount;
      const type = closed.summary?.variance_type ?? closed.variance_type;
      setSession(null);
      setSummary(null);
      setCart([]);
      setShowCloseForm(false);
      setClosingCash("");
      setClosingNotes("");
      setMessage(
        type && type !== "balanced"
          ? `Session closed · variance ${money(variance)} (${type})`
          : "Session closed · cash balanced.",
      );
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to close session.");
    } finally {
      setBusy(false);
    }
  };

  const loadLoyaltyForCustomer = async (customerId: string) => {
    try {
      const account = await lookupPosLoyalty({ customer_id: customerId });
      if (!account) {
        setLoyalty(null);
        return;
      }
      setLoyalty({
        id: account.id,
        loyalty_number: account.loyalty_number,
        points_balance: account.points_balance,
        tier: account.tier?.name ?? null,
      });
      const rewards = await fetchLoyaltyRewards();
      setLoyaltyRewards(
        rewards
          .filter((r) => r.is_active)
          .map((r) => ({ id: r.id, name: r.name, points_cost: r.points_cost })),
      );
    } catch {
      setLoyalty(null);
    }
  };

  const searchCustomers = async () => {
    try {
      setCustomers(await searchPosCustomers(customerQuery));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Customer search failed.");
    }
  };

  const redeemLoyaltyReward = async (rewardId: string) => {
    if (!loyalty) return;
    setBusy(true);
    setError(null);
    try {
      const result = await redeemPosLoyalty(loyalty.id, rewardId);
      if (result.data.promotion_code) {
        setPromotionCode(result.data.promotion_code);
      }
      setLoyalty({
        id: result.data.account.id,
        loyalty_number: result.data.account.loyalty_number,
        points_balance: result.data.account.points_balance,
        tier: result.data.account.tier?.name ?? null,
      });
      setMessage(
        result.data.promotion_code
          ? `Loyalty redeemed. Code ${result.data.promotion_code} applied to promo field.`
          : "Loyalty reward redeemed.",
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : "Loyalty redeem failed.");
    } finally {
      setBusy(false);
    }
  };

  const completeSale = async () => {
    if (cart.length === 0) return;
    setBusy(true);
    setError(null);
    setLastSale(null);
    try {
      const method = methods.find((m) => m.code === paymentCode);
      const handler = method?.config?.handler ?? "manual_confirm";
      const result = await completePosSale({
        items: cart.map((line) => ({
          product_id: line.product_id,
          product_variant_id: line.product_variant_id,
          quantity: line.quantity,
        })),
        payment_method: paymentCode,
        amount_received:
          handler === "cash_with_change" ? Number(amountReceived || quote?.grand_total || 0) : undefined,
        manual_confirmed: handler !== "cash_with_change" ? true : undefined,
        customer_id: customer?.id,
        promotion_code: promotionCode || undefined,
      });
      setLastSale(
        `${result.data.order.order_number} · ${result.data.receipt.receipt_number}` +
          (result.data.change ? ` · Change ${money(result.data.change)}` : ""),
      );
      setLastReceipt(result.data.receipt);
      if (result.data.session_summary) {
        setSummary(result.data.session_summary);
      }
      setCart([]);
      setPromotionCode("");
      setAmountReceived("");
      setCustomer(null);
      setLoyalty(null);
      setLoyaltyRewards([]);
      setMessage("Sale completed.");
      await refresh();
      await searchPosCatalog(query).then(setResults);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Sale failed.");
    } finally {
      setBusy(false);
    }
  };

  const displayTotal = quote?.grand_total ?? cart.reduce((s, l) => s + Number(l.line_total), 0).toFixed(2);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-zinc-200 pb-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900">POS</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Cashier session · store-scoped TZ_LOCAL selling
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <Link href="/admin/pos/returns" className="rounded-md border border-zinc-300 px-2.5 py-1 text-zinc-700">
            Returns
          </Link>
          <Link href="/admin/pos/receipts" className="rounded-md border border-zinc-300 px-2.5 py-1 text-zinc-700">
            Receipts
          </Link>
          <Link href="/admin/pos/sessions" className="rounded-md border border-zinc-300 px-2.5 py-1 text-zinc-700">
            Manager sessions
          </Link>
          <span className="rounded-md bg-zinc-100 px-2.5 py-1 font-medium text-zinc-800">
            Store: {selectedStore?.name ?? "—"}
          </span>
          <span
            className={`rounded-md px-2.5 py-1 font-medium ${
              session ? "bg-emerald-50 text-emerald-800" : "bg-amber-50 text-amber-800"
            }`}
          >
            Session: {session ? "OPEN" : "CLOSED"}
          </span>
        </div>
      </div>

      {error ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
      ) : null}
      {message ? (
        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {message}
        </div>
      ) : null}
      {lastSale ? (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800">
          <span>Last sale: {lastSale}</span>
          {lastReceipt ? (
            <button
              type="button"
              disabled={busy}
              className="rounded-md border border-zinc-300 bg-white px-3 py-1 text-sm"
              onClick={async () => {
                try {
                  const printed = await printPosReceipt(lastReceipt.id, "thermal_80");
                  const win = window.open("", "_blank", "noopener,noreferrer,width=420,height=720");
                  if (!win) return;
                  win.document.write(printed.data.html);
                  win.document.close();
                  win.focus();
                  setTimeout(() => win.print(), 250);
                } catch (e) {
                  setError(e instanceof Error ? e.message : "Unable to print receipt.");
                }
              }}
            >
              Print receipt
            </button>
          ) : null}
        </div>
      ) : null}

      {!session ? (
        <div className="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 bg-white p-4">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-zinc-700">Active store</span>
            <select
              className="min-w-[220px] rounded-md border border-zinc-300 px-3 py-2"
              value={selectedStoreId}
              onChange={(e) => setSelectedStoreId(e.target.value)}
            >
              {stores.map((store) => (
                <option key={store.id} value={store.id}>
                  {store.name}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-zinc-700">Opening float (TZS)</span>
            <input
              type="number"
              min={0}
              value={openingFloat}
              onChange={(e) => setOpeningFloat(e.target.value)}
              className="w-40 rounded-md border border-zinc-300 px-3 py-2"
            />
          </label>
          <button
            type="button"
            disabled={busy || stores.length === 0}
            onClick={openSession}
            className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          >
            Open session
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <DashStat label="Opening float" value={money(summary?.opening_float)} />
            <DashStat label="Cash sales" value={money(summary?.cash_sales)} />
            <DashStat label="Expected cash" value={money(summary?.expected_cash)} />
            <DashStat label="Transactions" value={String(summary?.transaction_count ?? 0)} />
          </div>
          {summary?.payment_breakdown?.length ? (
            <div className="flex flex-wrap gap-2 text-sm">
              {summary.payment_breakdown.map((row) => (
                <span key={row.code} className="rounded-md bg-zinc-100 px-2.5 py-1 text-zinc-700">
                  {row.name}: {money(row.amount)} ({row.count})
                </span>
              ))}
            </div>
          ) : null}

          {!showCloseForm ? (
            <div className="flex justify-end">
              <button
                type="button"
                disabled={busy}
                onClick={() => {
                  setClosingCash(summary?.expected_cash ?? "0");
                  setShowCloseForm(true);
                }}
                className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm text-zinc-700"
              >
                Close session
              </button>
            </div>
          ) : (
            <div className="rounded-lg border border-zinc-200 bg-white p-4">
              <h2 className="text-sm font-semibold text-zinc-900">Cash count & close</h2>
              <p className="mt-1 text-sm text-zinc-500">
                Expected cash drawer: {money(summary?.expected_cash)}
              </p>
              <div className="mt-3 flex flex-wrap items-end gap-3">
                <label className="flex flex-col gap-1 text-sm">
                  <span className="font-medium text-zinc-700">Actual cash count</span>
                  <input
                    type="number"
                    min={0}
                    value={closingCash}
                    onChange={(e) => setClosingCash(e.target.value)}
                    className="w-44 rounded-md border border-zinc-300 px-3 py-2"
                  />
                </label>
                {Number(closingCash || 0) !== Number(summary?.expected_cash || 0) ? (
                  <label className="flex flex-col gap-1 text-sm">
                    <span className="font-medium text-zinc-700">Variance reason</span>
                    <select
                      value={varianceReason}
                      onChange={(e) => setVarianceReason(e.target.value)}
                      className="rounded-md border border-zinc-300 px-3 py-2"
                    >
                      <option value="customer_change_mistake">Customer change mistake</option>
                      <option value="cash_counting_error">Cash counting error</option>
                      <option value="other">Other</option>
                    </select>
                  </label>
                ) : null}
                <label className="flex min-w-[220px] flex-1 flex-col gap-1 text-sm">
                  <span className="font-medium text-zinc-700">Notes</span>
                  <input
                    value={closingNotes}
                    onChange={(e) => setClosingNotes(e.target.value)}
                    className="rounded-md border border-zinc-300 px-3 py-2"
                    placeholder="Optional"
                  />
                </label>
                <button
                  type="button"
                  disabled={busy}
                  onClick={closeSession}
                  className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                  Confirm close
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => setShowCloseForm(false)}
                  className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
                >
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {session ? (
        <div className="grid gap-4 lg:grid-cols-[1.2fr_0.9fr]">
          <section className="space-y-3 rounded-lg border border-zinc-200 bg-white p-4">
            <label className="block text-sm font-medium text-zinc-700">
              Search product (name, SKU, barcode)
              <input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Black dress / ZD-001 / barcode"
                className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"
              />
            </label>
            <div className="max-h-[520px] space-y-2 overflow-y-auto">
              {results.map((item) => (
                <button
                  key={item.product_variant_id}
                  type="button"
                  disabled={!item.in_stock}
                  onClick={() => addToCart(item)}
                  className="flex w-full items-start justify-between gap-3 rounded-md border border-zinc-200 px-3 py-2.5 text-left hover:border-zinc-400 disabled:opacity-40"
                >
                  <div>
                    <p className="font-medium text-zinc-900">{item.product_name}</p>
                    <p className="text-xs text-zinc-500">
                      {item.variant_name || "Default"} · {item.variant_sku || item.product_sku}
                      {item.barcode ? ` · ${item.barcode}` : ""}
                    </p>
                    <p className="mt-1 text-xs text-zinc-500">Stock: {item.available_stock}</p>
                  </div>
                  <p className="shrink-0 text-sm font-semibold text-zinc-900">{money(item.unit_price)}</p>
                </button>
              ))}
              {results.length === 0 ? (
                <p className="py-8 text-center text-sm text-zinc-500">No products match this store catalog.</p>
              ) : null}
            </div>
          </section>

          <section className="space-y-4 rounded-lg border border-zinc-200 bg-white p-4">
            <div>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-zinc-500">Cart</h2>
              <div className="mt-2 space-y-2">
                {cart.map((line) => (
                  <div key={line.product_variant_id} className="rounded-md border border-zinc-100 px-2 py-2">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="text-sm font-medium text-zinc-900">{line.product_name}</p>
                        <p className="text-xs text-zinc-500">{line.variant_name || line.variant_sku}</p>
                      </div>
                      <button
                        type="button"
                        className="text-xs text-red-600"
                        onClick={() => updateQty(line.product_variant_id, 0)}
                      >
                        Remove
                      </button>
                    </div>
                    <div className="mt-2 flex items-center justify-between gap-2">
                      <div className="flex items-center gap-1">
                        <button
                          type="button"
                          className="h-7 w-7 rounded border border-zinc-300 text-sm"
                          onClick={() => updateQty(line.product_variant_id, line.quantity - 1)}
                        >
                          −
                        </button>
                        <span className="w-8 text-center text-sm">{line.quantity}</span>
                        <button
                          type="button"
                          className="h-7 w-7 rounded border border-zinc-300 text-sm"
                          onClick={() => updateQty(line.product_variant_id, line.quantity + 1)}
                        >
                          +
                        </button>
                      </div>
                      <p className="text-sm font-medium">{money(line.line_total)}</p>
                    </div>
                  </div>
                ))}
                {cart.length === 0 ? <p className="text-sm text-zinc-500">Cart is empty.</p> : null}
              </div>
            </div>

            <div className="space-y-2 border-t border-zinc-100 pt-3">
              <label className="block text-sm text-zinc-700">
                Customer (optional)
                <div className="mt-1 flex gap-2">
                  <input
                    value={customerQuery}
                    onChange={(e) => setCustomerQuery(e.target.value)}
                    placeholder="Search CRM customer"
                    className="w-full rounded-md border border-zinc-300 px-3 py-2"
                  />
                  <button
                    type="button"
                    onClick={searchCustomers}
                    className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
                  >
                    Find
                  </button>
                </div>
              </label>
              {customer ? (
                <div className="flex items-center justify-between rounded-md bg-zinc-50 px-2 py-1.5 text-sm">
                  <span>
                    {customer.name} · {customer.email}
                  </span>
                  <button
                    type="button"
                    className="text-xs text-zinc-600"
                    onClick={() => {
                      setCustomer(null);
                      setLoyalty(null);
                      setLoyaltyRewards([]);
                    }}
                  >
                    Clear
                  </button>
                </div>
              ) : (
                <p className="text-xs text-zinc-500">Walk-in Customer (no loyalty)</p>
              )}
              {loyalty ? (
                <div className="rounded-md border border-amber-200 bg-amber-50 px-2 py-2 text-xs text-amber-950">
                  <p className="font-medium">
                    Loyalty {loyalty.loyalty_number} · {loyalty.points_balance} pts
                    {loyalty.tier ? ` · ${loyalty.tier}` : ""}
                  </p>
                  {loyaltyRewards.length > 0 ? (
                    <div className="mt-1 flex flex-wrap gap-1">
                      {loyaltyRewards.slice(0, 4).map((r) => (
                        <button
                          key={r.id}
                          type="button"
                          disabled={busy || loyalty.points_balance < r.points_cost}
                          onClick={() => void redeemLoyaltyReward(r.id)}
                          className="rounded border border-amber-300 bg-white px-1.5 py-0.5 disabled:opacity-40"
                        >
                          {r.name} ({r.points_cost})
                        </button>
                      ))}
                    </div>
                  ) : null}
                </div>
              ) : null}
              {customers.length > 0 ? (
                <ul className="max-h-28 overflow-y-auto rounded-md border border-zinc-200">
                  {customers.map((c) => (
                    <li key={c.id}>
                      <button
                        type="button"
                        className="block w-full px-2 py-1.5 text-left text-sm hover:bg-zinc-50"
                        onClick={() => {
                          setCustomer(c);
                          setCustomers([]);
                          void loadLoyaltyForCustomer(c.id);
                        }}
                      >
                        {c.name} · {c.email}
                      </button>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>

            <label className="block text-sm text-zinc-700">
              Promotion code
              <input
                value={promotionCode}
                onChange={(e) => setPromotionCode(e.target.value)}
                className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"
                placeholder="Optional"
              />
            </label>

            <div className="space-y-1 border-t border-zinc-100 pt-3 text-sm">
              <div className="flex justify-between text-zinc-600">
                <span>Subtotal</span>
                <span>{money(quote?.subtotal ?? displayTotal)}</span>
              </div>
              <div className="flex justify-between text-zinc-600">
                <span>Discount</span>
                <span>{money(quote?.discount_total ?? 0)}</span>
              </div>
              {quote?.promotion?.promotion_name ? (
                <p className="text-xs text-emerald-700">{quote.promotion.promotion_name}</p>
              ) : null}
              <div className="flex justify-between text-base font-semibold text-zinc-900">
                <span>TOTAL</span>
                <span>{money(displayTotal)}</span>
              </div>
            </div>

            <div>
              <p className="mb-2 text-sm font-medium text-zinc-700">Payment methods</p>
              <div className="flex flex-wrap gap-2">
                {methods.map((method) => (
                  <button
                    key={method.id}
                    type="button"
                    onClick={() => setPaymentCode(method.code)}
                    className={`rounded-md px-3 py-2 text-sm font-medium ${
                      paymentCode === method.code
                        ? "bg-zinc-900 text-white"
                        : "border border-zinc-300 text-zinc-700"
                    }`}
                  >
                    {method.name}
                  </button>
                ))}
              </div>
            </div>

            {methods.find((m) => m.code === paymentCode)?.config?.handler === "cash_with_change" ? (
              <label className="block text-sm text-zinc-700">
                Amount received
                <input
                  type="number"
                  min={0}
                  value={amountReceived}
                  onChange={(e) => setAmountReceived(e.target.value)}
                  placeholder={displayTotal}
                  className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"
                />
              </label>
            ) : (
              <p className="text-xs text-zinc-500">Manual confirmation required at complete.</p>
            )}

            <button
              type="button"
              disabled={busy || cart.length === 0}
              onClick={completeSale}
              className="w-full rounded-md bg-[#1f4b3a] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
            >
              Complete sale
            </button>
          </section>
        </div>
      ) : null}
    </div>
  );
}

function DashStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border border-zinc-200 bg-white px-3 py-2.5">
      <p className="text-xs uppercase tracking-wide text-zinc-500">{label}</p>
      <p className="mt-1 text-sm font-semibold text-zinc-900">{value}</p>
    </div>
  );
}
