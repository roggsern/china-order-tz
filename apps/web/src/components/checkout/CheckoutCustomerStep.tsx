"use client";

import type { CheckoutFormData, CheckoutFormErrors } from "@/lib/types/checkout";
import { formatTanzaniaPhone } from "@/lib/checkout/phone";
import { CheckoutField, checkoutInputClassName } from "./CheckoutField";
import { SearchableRegionSelect } from "./SearchableRegionSelect";

interface CheckoutCustomerStepProps {
  form: CheckoutFormData;
  errors: CheckoutFormErrors;
  fullName: string;
  onFullNameChange: (value: string) => void;
  onCustomerChange: (customer: CheckoutFormData["customer"]) => void;
  onAddressChange: (address: CheckoutFormData["shippingAddress"]) => void;
  onBlurField?: (field: "fullName" | "phone" | "email" | "addressLine1" | "city" | "region") => void;
  onClearError?: (scope: "customer" | "shippingAddress", field: string) => void;
}

export function CheckoutCustomerStep({
  form,
  errors,
  fullName,
  onFullNameChange,
  onCustomerChange,
  onAddressChange,
  onBlurField,
  onClearError,
}: CheckoutCustomerStepProps) {
  return (
    <div className="space-y-5">
      <CheckoutField
        id="checkout-full-name"
        label="Full Name"
        required
        error={errors.customer?.firstName ?? errors.customer?.lastName}
      >
        <input
          id="checkout-full-name"
          type="text"
          autoComplete="name"
          value={fullName}
          onChange={(event) => {
            onFullNameChange(event.target.value);
            onClearError?.("customer", "firstName");
            onClearError?.("customer", "lastName");
          }}
          onBlur={() => onBlurField?.("fullName")}
          placeholder="John Mwangi"
          className={checkoutInputClassName(
            Boolean(errors.customer?.firstName || errors.customer?.lastName),
          )}
          aria-invalid={Boolean(errors.customer?.firstName || errors.customer?.lastName)}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-phone"
        label="Phone Number"
        required
        error={errors.customer?.phone}
        hint="Tanzania mobile — formatted automatically"
      >
        <input
          id="checkout-phone"
          type="tel"
          autoComplete="tel"
          inputMode="tel"
          value={form.customer.phone}
          onChange={(event) => {
            onCustomerChange({ ...form.customer, phone: event.target.value });
            onClearError?.("customer", "phone");
          }}
          onBlur={() => {
            const formatted = formatTanzaniaPhone(form.customer.phone);
            if (formatted !== form.customer.phone) {
              onCustomerChange({ ...form.customer, phone: formatted });
            }
            onBlurField?.("phone");
          }}
          placeholder="0712345678"
          className={checkoutInputClassName(Boolean(errors.customer?.phone))}
          aria-invalid={Boolean(errors.customer?.phone)}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-email"
        label="Email"
        error={errors.customer?.email}
        hint="Optional — for order confirmation"
      >
        <input
          id="checkout-email"
          type="email"
          autoComplete="email"
          value={form.customer.email}
          onChange={(event) => {
            onCustomerChange({ ...form.customer, email: event.target.value });
            onClearError?.("customer", "email");
          }}
          onBlur={() => onBlurField?.("email")}
          placeholder="you@example.com"
          className={checkoutInputClassName(Boolean(errors.customer?.email))}
          aria-invalid={Boolean(errors.customer?.email)}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-address-line1"
        label="Address / Location"
        required
        error={errors.shippingAddress?.addressLine1}
      >
        <input
          id="checkout-address-line1"
          type="text"
          autoComplete="street-address"
          value={form.shippingAddress.addressLine1}
          onChange={(event) => {
            onAddressChange({ ...form.shippingAddress, addressLine1: event.target.value });
            onClearError?.("shippingAddress", "addressLine1");
          }}
          onBlur={() => onBlurField?.("addressLine1")}
          placeholder="Plot 12, Sam Nujoma Road"
          className={checkoutInputClassName(Boolean(errors.shippingAddress?.addressLine1))}
          aria-invalid={Boolean(errors.shippingAddress?.addressLine1)}
        />
      </CheckoutField>

      <div className="grid gap-5 sm:grid-cols-2">
        <CheckoutField id="checkout-city" label="City" required error={errors.shippingAddress?.city}>
          <input
            id="checkout-city"
            type="text"
            autoComplete="address-level2"
            value={form.shippingAddress.city}
            onChange={(event) => {
              onAddressChange({ ...form.shippingAddress, city: event.target.value });
              onClearError?.("shippingAddress", "city");
            }}
            onBlur={() => onBlurField?.("city")}
            placeholder="Dar es Salaam"
            className={checkoutInputClassName(Boolean(errors.shippingAddress?.city))}
            aria-invalid={Boolean(errors.shippingAddress?.city)}
          />
        </CheckoutField>

        <CheckoutField id="checkout-region" label="Region" required error={errors.shippingAddress?.region}>
          <SearchableRegionSelect
            id="checkout-region"
            value={form.shippingAddress.region}
            onChange={(region) => {
              onAddressChange({ ...form.shippingAddress, region });
              onClearError?.("shippingAddress", "region");
            }}
            onBlur={() => onBlurField?.("region")}
            error={errors.shippingAddress?.region}
          />
        </CheckoutField>
      </div>
    </div>
  );
}
