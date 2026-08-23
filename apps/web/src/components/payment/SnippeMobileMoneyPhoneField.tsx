"use client";

import { formatPhoneOnBlur } from "@/lib/phone";
import {
  SNIPPE_PHONE_HELPER,
  SNIPPE_PHONE_LABEL,
} from "@/lib/payment/snippe";
import { CheckoutField, checkoutInputClassName } from "@/components/checkout/CheckoutField";

interface SnippeMobileMoneyPhoneFieldProps {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  error?: string;
}

export function SnippeMobileMoneyPhoneField({
  value,
  onChange,
  disabled = false,
  error,
}: SnippeMobileMoneyPhoneFieldProps) {
  return (
    <CheckoutField
      id="snippe-mobile-money-number"
      label={SNIPPE_PHONE_LABEL}
      required
      error={error}
      hint={SNIPPE_PHONE_HELPER}
    >
      <input
        id="snippe-mobile-money-number"
        name="snippe-mobile-money-number"
        type="tel"
        inputMode="tel"
        autoComplete="tel"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        onBlur={() => onChange(formatPhoneOnBlur(value))}
        disabled={disabled}
        placeholder="0712345678 or +255712345678"
        className={checkoutInputClassName(Boolean(error))}
        aria-invalid={Boolean(error)}
        aria-describedby={
          error
            ? "snippe-mobile-money-number-error"
            : "snippe-mobile-money-number-hint"
        }
      />
    </CheckoutField>
  );
}
