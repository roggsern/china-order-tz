"use client";

import { useCallback, useEffect, useState } from "react";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  DeliveryOptionApiError,
  fetchDeliveryOption,
  selectDeliveryOption,
  updateDeliveryOption,
  type DeliveryAvailableOptions,
  type DeliveryOptionPayload,
} from "@/lib/api/customer-delivery-option";

interface OrderDeliveryOptionPanelProps {
  orderNumber: string;
  /** When true, user may select/update (paid+). */
  canSelect: boolean;
}

export function OrderDeliveryOptionPanel({
  orderNumber,
  canSelect,
}: OrderDeliveryOptionPanelProps) {
  const [option, setOption] = useState<DeliveryOptionPayload | null>(null);
  const [available, setAvailable] = useState<DeliveryAvailableOptions | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [deliveryType, setDeliveryType] = useState("");
  const [shippingMethod, setShippingMethod] = useState<"air" | "sea" | "">("");
  const [agentName, setAgentName] = useState("");
  const [agentContact, setAgentContact] = useState("");
  const [notes, setNotes] = useState("");

  const reload = useCallback(async () => {
    const token = getCustomerApiToken();
    if (!token) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const data = await fetchDeliveryOption(orderNumber, token);
      setOption(data.delivery_option);
      setAvailable(data.available);
      if (data.delivery_option) {
        setDeliveryType(data.delivery_option.delivery_type);
        setShippingMethod(
          (data.delivery_option.shipping_method as "air" | "sea" | null) ?? "",
        );
        setAgentName(data.delivery_option.agent_name ?? "");
        setAgentContact(data.delivery_option.agent_contact ?? "");
        setNotes(data.delivery_option.notes ?? "");
      } else if (data.available.delivery_types[0]) {
        setDeliveryType(data.available.delivery_types[0].value);
      }
    } catch (err) {
      setError(
        err instanceof DeliveryOptionApiError
          ? err.message
          : "Unable to load delivery options.",
      );
    } finally {
      setLoading(false);
    }
  }, [orderNumber]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const handleSubmit = async () => {
    const token = getCustomerApiToken();
    if (!token || !deliveryType || busy) return;

    setBusy(true);
    setError(null);

    const body = {
      delivery_type: deliveryType,
      shipping_method:
        deliveryType === "company_shipping" ? shippingMethod || null : null,
      agent_name: deliveryType === "customer_agent" ? agentName : null,
      agent_contact: deliveryType === "customer_agent" ? agentContact : null,
      notes: notes || null,
    };

    try {
      const next = option
        ? await updateDeliveryOption(orderNumber, body, token)
        : await selectDeliveryOption(orderNumber, body, token);
      setOption(next);
    } catch (err) {
      setError(
        err instanceof DeliveryOptionApiError
          ? err.message
          : "Unable to save delivery option.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleConfirm = async () => {
    const token = getCustomerApiToken();
    if (!token || !option || busy) return;
    setBusy(true);
    setError(null);
    try {
      const next = await updateDeliveryOption(
        orderNumber,
        { delivery_status: "confirmed" },
        token,
      );
      setOption(next);
    } catch (err) {
      setError(
        err instanceof DeliveryOptionApiError
          ? err.message
          : "Unable to confirm delivery option.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return (
      <section className="rounded-3xl border border-zinc-200/70 bg-white p-5 sm:p-7">
        <p className="text-sm text-zinc-500">Loading delivery options…</p>
      </section>
    );
  }

  if (!available) {
    return null;
  }

  const isChina = available.market === "china";
  const locked = option?.delivery_status === "completed";
  const showForm = canSelect && !locked;

  return (
    <section
      aria-labelledby="delivery-option-heading"
      className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
    >
      <h2 id="delivery-option-heading" className="text-lg font-bold text-zinc-900">
        Delivery handoff
      </h2>
      <p className="mt-1 text-sm text-zinc-500">
        Shipping choice was locked at checkout before payment. You can add agent contact details or
        confirm handoff status here — this does not change the amount paid.
      </p>

      {option ? (
        <div className="mt-4 rounded-2xl border border-zinc-100 bg-zinc-50/80 px-4 py-3 text-sm">
          <p className="font-semibold text-zinc-900">
            {option.delivery_type_label ?? option.delivery_type}
          </p>
          {option.shipping_method_label ? (
            <p className="mt-1 text-zinc-600">{option.shipping_method_label}</p>
          ) : null}
          {option.agent_name ? (
            <p className="mt-1 text-zinc-600">
              Agent: {option.agent_name}
              {option.agent_contact ? ` · ${option.agent_contact}` : ""}
            </p>
          ) : null}
          <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            Status: {option.delivery_status_label ?? option.delivery_status}
          </p>
        </div>
      ) : (
        <p className="mt-4 text-sm text-amber-800">
          No delivery option on this order yet.
          {canSelect ? " You can record a legacy handoff below." : " Complete payment first if this is an older order."}
        </p>
      )}

      {showForm ? (
        <div className="mt-5 space-y-4">
          {!option ? (
            <fieldset>
              <legend className="text-xs font-bold uppercase tracking-[0.12em] text-zinc-500">
                {isChina ? "China delivery" : "Tanzania delivery"}
              </legend>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {available.delivery_types.map((type) => (
                  <label
                    key={type.value}
                    className={`cursor-pointer rounded-xl border px-3 py-3 text-sm transition ${
                      deliveryType === type.value
                        ? "border-[#c9a227] bg-[#c9a227]/10 font-semibold text-zinc-900"
                        : "border-zinc-200 text-zinc-700 hover:border-zinc-300"
                    }`}
                  >
                    <input
                      type="radio"
                      name="delivery_type"
                      value={type.value}
                      checked={deliveryType === type.value}
                      onChange={() => setDeliveryType(type.value)}
                      className="sr-only"
                    />
                    {type.label}
                  </label>
                ))}
              </div>
            </fieldset>
          ) : null}

          {!option && deliveryType === "company_shipping" ? (
            <fieldset>
              <legend className="text-xs font-bold uppercase tracking-[0.12em] text-zinc-500">
                Shipping method
              </legend>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {available.shipping_methods.map((method) => (
                  <label
                    key={method.value}
                    className={`cursor-pointer rounded-xl border px-3 py-3 text-sm transition ${
                      shippingMethod === method.value
                        ? "border-[#c9a227] bg-[#c9a227]/10 font-semibold text-zinc-900"
                        : "border-zinc-200 text-zinc-700 hover:border-zinc-300"
                    }`}
                  >
                    <input
                      type="radio"
                      name="shipping_method"
                      value={method.value}
                      checked={shippingMethod === method.value}
                      onChange={() =>
                        setShippingMethod(method.value as "air" | "sea")
                      }
                      className="sr-only"
                    />
                    {method.label}
                  </label>
                ))}
              </div>
            </fieldset>
          ) : null}

          {deliveryType === "customer_agent" ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block text-sm">
                <span className="font-medium text-zinc-700">Agent name</span>
                <input
                  value={agentName}
                  onChange={(e) => setAgentName(e.target.value)}
                  className="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2"
                  placeholder="Clearing agent / freight forwarder"
                />
              </label>
              <label className="block text-sm">
                <span className="font-medium text-zinc-700">Agent contact</span>
                <input
                  value={agentContact}
                  onChange={(e) => setAgentContact(e.target.value)}
                  className="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2"
                  placeholder="+255…"
                />
              </label>
            </div>
          ) : null}

          {!option && deliveryType === "self_pickup" ? (
            <p className="text-sm text-zinc-600">
              Self pickup request will be recorded. Collect from our warehouse when ready.
            </p>
          ) : null}

          {!option && deliveryType === "negotiated_delivery" ? (
            <p className="text-sm text-zinc-600">
              Delivery price is negotiated offline. No automatic courier assignment.
            </p>
          ) : null}

          <label className="block text-sm">
            <span className="font-medium text-zinc-700">Notes (optional)</span>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={2}
              className="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2"
            />
          </label>

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              disabled={busy}
              onClick={() => void handleSubmit()}
              className="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-50"
            >
              {busy ? "Saving…" : option ? "Update delivery option" : "Save delivery option"}
            </button>
            {option && option.delivery_status === "pending" ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => void handleConfirm()}
                className="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-semibold text-zinc-800 hover:border-[#c9a227]/50 disabled:opacity-50"
              >
                Confirm selection
              </button>
            ) : null}
          </div>
        </div>
      ) : null}

      {error ? (
        <p className="mt-3 text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}
    </section>
  );
}
