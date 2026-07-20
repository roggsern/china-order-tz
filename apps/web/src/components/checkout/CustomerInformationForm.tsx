"use client";

import type { CustomerInformation, CheckoutFormErrors } from "@/lib/types/checkout";
import { formatPhoneOnBlur } from "@/lib/phone";
import { CheckoutField, checkoutInputClassName } from "./CheckoutField";

interface CustomerInformationFormProps {
  value: CustomerInformation;
  errors?: CheckoutFormErrors["customer"];
  onChange: (value: CustomerInformation) => void;
  onBlurField?: (field: keyof CustomerInformation) => void;
  onClearError?: (field: keyof CustomerInformation) => void;
}

export function CustomerInformationForm({
  value,
  errors,
  onChange,
  onBlurField,
  onClearError,
}: CustomerInformationFormProps) {
  const update = (field: keyof CustomerInformation, fieldValue: string) => {
    onChange({ ...value, [field]: fieldValue });
    onClearError?.(field);
  };

  return (
    <div className="grid gap-5 sm:grid-cols-2">
      <CheckoutField
        id="checkout-first-name"
        label="First Name"
        required
        error={errors?.firstName}
      >
        <input
          id="checkout-first-name"
          type="text"
          autoComplete="given-name"
          value={value.firstName}
          onChange={(event) => update("firstName", event.target.value)}
          onBlur={() => onBlurField?.("firstName")}
          placeholder="John"
          className={checkoutInputClassName(Boolean(errors?.firstName))}
          aria-invalid={Boolean(errors?.firstName)}
          aria-describedby={errors?.firstName ? "checkout-first-name-error" : undefined}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-last-name"
        label="Last Name"
        required
        error={errors?.lastName}
      >
        <input
          id="checkout-last-name"
          type="text"
          autoComplete="family-name"
          value={value.lastName}
          onChange={(event) => update("lastName", event.target.value)}
          onBlur={() => onBlurField?.("lastName")}
          placeholder="Mwangi"
          className={checkoutInputClassName(Boolean(errors?.lastName))}
          aria-invalid={Boolean(errors?.lastName)}
          aria-describedby={errors?.lastName ? "checkout-last-name-error" : undefined}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-email"
        label="Email address"
        required
        error={errors?.email}
      >
        <input
          id="checkout-email"
          type="email"
          autoComplete="email"
          value={value.email}
          onChange={(event) => update("email", event.target.value)}
          onBlur={() => onBlurField?.("email")}
          placeholder="you@example.com"
          className={checkoutInputClassName(Boolean(errors?.email))}
          aria-invalid={Boolean(errors?.email)}
          aria-describedby={errors?.email ? "checkout-email-error" : undefined}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-phone"
        label="Phone number"
        required
        error={errors?.phone}
        hint="International mobile — formatted to +country code on blur"
      >
        <input
          id="checkout-phone"
          type="tel"
          autoComplete="tel"
          inputMode="tel"
          value={value.phone}
          onChange={(event) => update("phone", event.target.value)}
          onBlur={() => {
            const formatted = formatPhoneOnBlur(value.phone);
            if (formatted !== value.phone) {
              onChange({ ...value, phone: formatted });
            }
            onBlurField?.("phone");
          }}
          placeholder="0712345678 or +255712345678"
          className={checkoutInputClassName(Boolean(errors?.phone))}
          aria-invalid={Boolean(errors?.phone)}
          aria-describedby={errors?.phone ? "checkout-phone-error" : undefined}
        />
      </CheckoutField>
    </div>
  );
}
