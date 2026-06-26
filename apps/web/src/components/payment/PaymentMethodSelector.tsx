"use client";

import type { PaymentCategory, PaymentMethodSelection } from "@/lib/types/payment";
import {
  BANK_TRANSFER_OPTION,
  CARD_OPTION,
  MOBILE_MONEY_OPTIONS,
  PAYMENT_CATEGORY_OPTIONS,
} from "@/lib/payment/constants";
import { PaymentCard } from "./PaymentCard";

interface PaymentMethodSelectorProps {
  value: PaymentMethodSelection | null;
  onChange: (selection: PaymentMethodSelection) => void;
  error?: string;
}

export function PaymentMethodSelector({ value, onChange, error }: PaymentMethodSelectorProps) {
  const activeCategory = value?.category ?? null;

  const handleCategorySelect = (category: PaymentCategory) => {
    if (category === "bank_transfer") {
      onChange({ category, code: BANK_TRANSFER_OPTION.code });
      return;
    }

    if (category === "card") {
      onChange({ category, code: CARD_OPTION.code });
      return;
    }

    const defaultMobile = MOBILE_MONEY_OPTIONS[0];
    onChange({ category, code: defaultMobile.code });
  };

  return (
    <div className="space-y-5">
      <div className="grid gap-3 sm:grid-cols-3">
        {PAYMENT_CATEGORY_OPTIONS.map((option) => (
          <PaymentCard
            key={option.category}
            title={option.label}
            description={option.description}
            icon={option.icon}
            selected={activeCategory === option.category}
            onSelect={() => handleCategorySelect(option.category)}
          />
        ))}
      </div>

      {activeCategory === "mobile_money" && (
        <div className="rounded-2xl border border-zinc-100 bg-zinc-50/60 p-4 sm:p-5">
          <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Select mobile wallet
          </p>
          <div className="mt-3 grid gap-2.5 sm:grid-cols-2">
            {MOBILE_MONEY_OPTIONS.map((option) => (
              <PaymentCard
                key={option.code}
                title={option.label}
                description={option.description}
                icon={option.icon}
                selected={value?.code === option.code}
                onSelect={() =>
                  onChange({ category: "mobile_money", code: option.code })
                }
                compact
              />
            ))}
          </div>
        </div>
      )}

      {error && (
        <p className="text-sm font-medium text-red-600" role="alert">
          {error}
        </p>
      )}

      <p className="text-xs leading-relaxed text-zinc-500">
        Payment providers are selectable for now. Gateway integration will be activated soon —
        no charges will be made at this stage.
      </p>
    </div>
  );
}
