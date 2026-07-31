"use client";

import { motion } from "framer-motion";
import type { CartLineItem } from "@/lib/types/cart";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { formatPrice } from "@/lib/catalog/utils";
import { LOCAL_DELIVERY_NEGOTIATED_LABEL } from "@/lib/catalog/product-type";
import { formatDeliveryEstimate, getMethodByCode } from "@/lib/shipping/engine";
import { getShippingQuote } from "@/lib/cart/shipping";
import type { CheckoutShippingChoice, CustomerAgentDetails } from "@/lib/checkout/shipping-choice";
import { TZ_LOCAL_DELIVERY_OPTIONS } from "@/lib/checkout/local-delivery-options";
import { CheckoutField, checkoutInputClassName } from "./CheckoutField";

interface CheckoutShippingStepProps {
  items: CartLineItem[];
  shippingChoice: CheckoutShippingChoice | null;
  selectedMethod: ShippingMethodCode | null;
  customerAgentDetails: CustomerAgentDetails;
  agentError?: string;
  onSelectChoice: (choice: CheckoutShippingChoice) => void;
  onSelectMethod: (method: ShippingMethodCode) => void;
  onAgentDetailsChange: (details: CustomerAgentDetails) => void;
  error?: string;
}

function computeShippingTotal(items: CartLineItem[], method: ShippingMethodCode): number {
  return items.reduce((sum, item) => {
    const methodForItem = item.origin === "tz" ? "local_delivery" : method;
    return sum + getShippingQuote(item, item.quantity, methodForItem).shippingTotal;
  }, 0);
}

export function CheckoutShippingStep({
  items,
  shippingChoice,
  selectedMethod,
  customerAgentDetails,
  agentError,
  onSelectChoice,
  onSelectMethod,
  onAgentDetailsChange,
  error,
}: CheckoutShippingStepProps) {
  const chinaItems = items.filter((item) => item.origin === "china");
  const tzItems = items.filter((item) => item.origin === "tz");
  const methods: ShippingMethodCode[] = ["air_freight", "sea_freight"];

  if (chinaItems.length === 0) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-zinc-600">
          Choose how you would like to receive your order. This is your collection preference — not
          a shipping quote or courier selection.
        </p>
        <div className="grid gap-3 sm:grid-cols-2">
          {TZ_LOCAL_DELIVERY_OPTIONS.map((option, index) => {
            const isSelected = shippingChoice === option.value;
            return (
              <motion.button
                key={option.value}
                type="button"
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25, delay: index * 0.06 }}
                onClick={() => onSelectChoice(option.value)}
                className={`flex h-full flex-col rounded-2xl border p-4 text-left transition duration-200 ${
                  isSelected
                    ? "border-[#c9a227] bg-[#c9a227]/5 shadow-sm ring-2 ring-[#c9a227]/30"
                    : "border-zinc-200 bg-white hover:-translate-y-0.5 hover:border-[#c9a227]/40 hover:shadow-md"
                }`}
                aria-pressed={isSelected}
              >
                <h3 className="text-base font-bold text-zinc-900">{option.title}</h3>
                <p className="mt-1 text-sm text-zinc-500">{option.description}</p>
              </motion.button>
            );
          })}
        </div>
        {error ? (
          <p
            role="alert"
            className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {error}
          </p>
        ) : null}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-zinc-600">
        Choose shipping before payment. Company freight is charged now; your own agent has zero
        company shipping.
      </p>

      <div className="grid gap-3 sm:grid-cols-2">
        <motion.button
          type="button"
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          onClick={() => onSelectChoice("company_shipping")}
          className={`rounded-2xl border p-4 text-left transition ${
            shippingChoice === "company_shipping"
              ? "border-[#c9a227] bg-[#c9a227]/5 ring-2 ring-[#c9a227]/30"
              : "border-zinc-200 bg-white hover:border-[#c9a227]/40"
          }`}
          aria-pressed={shippingChoice === "company_shipping"}
        >
          <h3 className="text-base font-bold text-zinc-900">CHINA ORDER TZ shipping</h3>
          <p className="mt-1 text-sm text-zinc-500">
            Air or sea freight. Cost is included in your order total before payment.
          </p>
        </motion.button>

        <motion.button
          type="button"
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.05 }}
          onClick={() => onSelectChoice("customer_agent")}
          className={`rounded-2xl border p-4 text-left transition ${
            shippingChoice === "customer_agent"
              ? "border-[#c9a227] bg-[#c9a227]/5 ring-2 ring-[#c9a227]/30"
              : "border-zinc-200 bg-white hover:border-[#c9a227]/40"
          }`}
          aria-pressed={shippingChoice === "customer_agent"}
        >
          <h3 className="text-base font-bold text-zinc-900">My own shipping agent</h3>
          <p className="mt-1 text-sm text-zinc-500">
            No company freight charge. Provide your agent details below.
          </p>
          <p className="mt-3 text-lg font-bold tabular-nums text-zinc-900">{formatPrice(0)}</p>
        </motion.button>
      </div>

      {shippingChoice === "company_shipping" ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {methods.map((methodCode, index) => {
            const method = getMethodByCode(methodCode);
            if (!method) return null;

            const isSelected = selectedMethod === methodCode;
            const shippingTotal = computeShippingTotal(items, methodCode);
            const estimate = formatDeliveryEstimate(methodCode);

            return (
              <motion.button
                key={methodCode}
                type="button"
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25, delay: index * 0.06 }}
                onClick={() => onSelectMethod(methodCode)}
                className={`flex h-full flex-col rounded-2xl border p-4 text-left transition duration-200 ${
                  isSelected
                    ? "border-[#c9a227] bg-[#c9a227]/5 shadow-sm ring-2 ring-[#c9a227]/30"
                    : "border-zinc-200 bg-white hover:-translate-y-0.5 hover:border-[#c9a227]/40 hover:shadow-md"
                }`}
                aria-pressed={isSelected}
              >
                <div className="flex items-start justify-between gap-2">
                  <span className="flex h-11 w-11 items-center justify-center rounded-xl border border-[#c9a227]/20 bg-white text-xl shadow-sm">
                    {method.icon}
                  </span>
                  {isSelected ? (
                    <span className="rounded-full bg-[#c9a227] px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-900">
                      Selected
                    </span>
                  ) : null}
                </div>

                <h3 className="mt-3 text-base font-bold text-zinc-900">{method.name}</h3>
                <p className="mt-1 text-sm text-zinc-500">{method.description}</p>

                <p className="mt-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
                  Estimated delivery
                </p>
                <p className="text-sm font-semibold text-[#8b6914]">
                  {estimate ? `Estimated delivery: ${estimate}` : "Delivery estimate unavailable"}
                </p>

                <p className="mt-3 text-lg font-bold tabular-nums text-zinc-900">
                  {formatPrice(shippingTotal)}
                </p>
              </motion.button>
            );
          })}
        </div>
      ) : null}

      {shippingChoice === "customer_agent" ? (
        <div className="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 sm:p-5">
          <h4 className="text-sm font-bold text-zinc-900">Shipping agent details</h4>
          <p className="mt-1 text-sm text-zinc-500">
            Required for your own agent. Address is optional.
          </p>

          <div className="mt-4 space-y-4">
            <CheckoutField
              id="checkout-agent-name"
              label="Agent name"
              required
            >
              <input
                id="checkout-agent-name"
                type="text"
                autoComplete="name"
                value={customerAgentDetails.name}
                onChange={(event) =>
                  onAgentDetailsChange({ ...customerAgentDetails, name: event.target.value })
                }
                placeholder="Agent or company name"
                className={checkoutInputClassName(false)}
                aria-invalid={Boolean(agentError && !customerAgentDetails.name.trim())}
              />
            </CheckoutField>

            <CheckoutField
              id="checkout-agent-phone"
              label="Agent phone number"
              required
            >
              <input
                id="checkout-agent-phone"
                type="tel"
                autoComplete="tel"
                inputMode="tel"
                value={customerAgentDetails.phone}
                onChange={(event) =>
                  onAgentDetailsChange({ ...customerAgentDetails, phone: event.target.value })
                }
                placeholder="0712345678 or +255712345678"
                className={checkoutInputClassName(false)}
                aria-invalid={Boolean(agentError && !customerAgentDetails.phone.trim())}
              />
            </CheckoutField>

            <CheckoutField
              id="checkout-agent-address"
              label="Agent address"
              hint="Optional"
            >
              <input
                id="checkout-agent-address"
                type="text"
                autoComplete="street-address"
                value={customerAgentDetails.address}
                onChange={(event) =>
                  onAgentDetailsChange({ ...customerAgentDetails, address: event.target.value })
                }
                placeholder="Office or warehouse location"
                className={checkoutInputClassName(false)}
              />
            </CheckoutField>
          </div>

          {agentError ? (
            <p
              role="alert"
              className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
              {agentError}
            </p>
          ) : null}
        </div>
      ) : null}

      {tzItems.length > 0 ? (
        <p className="text-xs text-zinc-500">
          {tzItems.length} local item{tzItems.length === 1 ? "" : "s"} —{" "}
          {LOCAL_DELIVERY_NEGOTIATED_LABEL.toLowerCase()}.
        </p>
      ) : null}

      {error ? (
        <p
          role="alert"
          className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
          {error}
        </p>
      ) : null}
    </div>
  );
}
