"use client";

import type { PaymentMethodCode } from "@/lib/types/payment";
import { SIMPLIFIED_PAYMENT_OPTIONS } from "@/lib/payment/constants";
import { PaymentCard } from "./PaymentCard";

export type PaymentSelectorOption = {
  code: PaymentMethodCode;
  label: string;
  description: string;
  icon: string;
};

interface SimplifiedPaymentMethodSelectorProps {
  value: PaymentMethodCode | null;
  onChange: (code: PaymentMethodCode) => void;
  error?: string;
  disabled?: boolean;
  /** When provided, only these options render (backend-filtered). */
  options?: PaymentSelectorOption[];
}

export function SimplifiedPaymentMethodSelector({
  value,
  onChange,
  error,
  disabled = false,
  options,
}: SimplifiedPaymentMethodSelectorProps) {
  const visibleOptions = options ?? SIMPLIFIED_PAYMENT_OPTIONS;

  return (
    <div className="space-y-4">
      <div className="grid gap-3">
        {visibleOptions.map((option) => (
          <PaymentCard
            key={option.code}
            title={option.label}
            description={option.description}
            icon={option.icon}
            selected={value === option.code}
            onSelect={disabled ? undefined : () => onChange(option.code)}
            disabled={disabled}
          />
        ))}
      </div>

      {visibleOptions.length === 0 ? (
        <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          No payment methods are available right now. Please contact support or try again later.
        </p>
      ) : null}

      {error ? (
        <p className="text-sm font-medium text-red-600" role="alert">
          {error}
        </p>
      ) : null}

      <p className="text-xs leading-relaxed text-zinc-500">
        Available methods are controlled by store payment settings. Gateway methods open a secure
        checkout; cash and bank transfer finalize after order placement.
      </p>
    </div>
  );
}
