"use client";

import type { PaymentMethodCode } from "@/lib/types/payment";
import { SIMPLIFIED_PAYMENT_OPTIONS } from "@/lib/payment/constants";
import { PaymentCard } from "./PaymentCard";

interface SimplifiedPaymentMethodSelectorProps {
  value: PaymentMethodCode | null;
  onChange: (code: PaymentMethodCode) => void;
  error?: string;
  disabled?: boolean;
}

export function SimplifiedPaymentMethodSelector({
  value,
  onChange,
  error,
  disabled = false,
}: SimplifiedPaymentMethodSelectorProps) {
  return (
    <div className="space-y-4">
      <div className="grid gap-3">
        {SIMPLIFIED_PAYMENT_OPTIONS.map((option) => (
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

      {error ? (
        <p className="text-sm font-medium text-red-600" role="alert">
          {error}
        </p>
      ) : null}

      <p className="text-xs leading-relaxed text-zinc-500">
        M-Pesa, NMB, and Selcom send a payment prompt to your phone or banking app. Cash on Delivery
        and Bank Transfer skip instant payment.
      </p>
    </div>
  );
}
