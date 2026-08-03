"use client";

import { useCallback, useState } from "react";
import {
  ReceivingChoiceApiError,
  selectReceivingMethod,
  type ReceivingChoiceSnapshot,
} from "@/lib/api/customer-receiving-choice";

type OrderReceivingChoicePanelProps = {
  orderNumber: string;
  receivingChoice: ReceivingChoiceSnapshot | null | undefined;
  onUpdated: () => Promise<void> | void;
};

function resolveSelectedLabel(method: string | null | undefined): string {
  if (method === "self_pickup") {
    return "Self Pickup";
  }

  if (method === "negotiated_delivery") {
    return "Delivery Arrangement";
  }

  return "Selected";
}

export function OrderReceivingChoicePanel({
  orderNumber,
  receivingChoice,
  onUpdated,
}: OrderReceivingChoicePanelProps) {
  const [busyMethod, setBusyMethod] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleSelect = useCallback(
    async (method: "self_pickup" | "negotiated_delivery") => {
      if (busyMethod) {
        return;
      }

      setBusyMethod(method);
      setError(null);

      try {
        await selectReceivingMethod(orderNumber, method);
        await onUpdated();
      } catch (err) {
        setError(
          err instanceof ReceivingChoiceApiError
            ? err.message
            : "Unable to save your receiving choice.",
        );
      } finally {
        setBusyMethod(null);
      }
    },
    [busyMethod, onUpdated, orderNumber],
  );

  if (!receivingChoice?.eligible && !receivingChoice?.selected_method) {
    return null;
  }

  return (
    <section
      aria-labelledby="receiving-choice-heading"
      className="rounded-3xl border border-[#c9a227]/30 bg-[#fffdf7] p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
    >
      <h2 id="receiving-choice-heading" className="text-lg font-bold text-zinc-900">
        Receive your order
      </h2>

      {receivingChoice.selected_method ? (
        <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
          <p className="text-sm font-semibold text-emerald-900">
            Selected: {resolveSelectedLabel(receivingChoice.selected_method)}
          </p>
          <p className="mt-1 text-sm text-emerald-800">
            We have recorded your receiving preference and will follow up with the next steps.
          </p>
        </div>
      ) : receivingChoice.can_select ? (
        <>
          <p className="mt-2 text-sm leading-relaxed text-zinc-600">
            Your order has arrived in Tanzania. Please choose how you would like to receive it.
          </p>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            <button
              type="button"
              disabled={busyMethod !== null}
              onClick={() => void handleSelect("self_pickup")}
              className="flex min-h-11 flex-col items-start rounded-xl bg-zinc-900 px-4 py-3 text-left transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <span className="text-sm font-semibold text-white">
                {busyMethod === "self_pickup" ? "Saving..." : "Self Pickup"}
              </span>
              <span className="mt-1 text-xs leading-relaxed text-zinc-300">
                I will collect my order from CHINA ORDER TZ
              </span>
            </button>
            <button
              type="button"
              disabled={busyMethod !== null}
              onClick={() => void handleSelect("negotiated_delivery")}
              className="flex min-h-11 flex-col items-start rounded-xl border border-zinc-300 bg-white px-4 py-3 text-left transition hover:border-[#c9a227]/60 hover:bg-[#fffdf7] disabled:cursor-not-allowed disabled:opacity-60"
            >
              <span className="text-sm font-semibold text-zinc-900">
                {busyMethod === "negotiated_delivery" ? "Saving..." : "Arrange Delivery"}
              </span>
              <span className="mt-1 text-xs leading-relaxed text-zinc-600">
                I want CHINA ORDER TZ to arrange delivery
              </span>
            </button>
          </div>
        </>
      ) : null}

      {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
    </section>
  );
}
