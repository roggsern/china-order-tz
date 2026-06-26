"use client";

import type { CheckoutFormErrors, ShippingAddress } from "@/lib/types/checkout";
import { CheckoutField, checkoutInputClassName } from "./CheckoutField";
import { SearchableRegionSelect } from "./SearchableRegionSelect";

interface ShippingAddressFormProps {
  value: ShippingAddress;
  errors?: CheckoutFormErrors["shippingAddress"];
  onChange: (value: ShippingAddress) => void;
  onBlurField?: (field: keyof ShippingAddress) => void;
  onClearError?: (field: keyof ShippingAddress) => void;
}

export function ShippingAddressForm({
  value,
  errors,
  onChange,
  onBlurField,
  onClearError,
}: ShippingAddressFormProps) {
  const update = (field: keyof ShippingAddress, fieldValue: string) => {
    onChange({ ...value, [field]: fieldValue });
    onClearError?.(field);
  };

  return (
    <div className="grid gap-5 sm:grid-cols-2">
      <CheckoutField
        id="checkout-address-line1"
        label="Street Address"
        required
        error={errors?.addressLine1}
        className="sm:col-span-2"
      >
        <input
          id="checkout-address-line1"
          type="text"
          autoComplete="address-line1"
          value={value.addressLine1}
          onChange={(event) => update("addressLine1", event.target.value)}
          onBlur={() => onBlurField?.("addressLine1")}
          placeholder="Plot 12, Sam Nujoma Road"
          className={checkoutInputClassName(Boolean(errors?.addressLine1))}
          aria-invalid={Boolean(errors?.addressLine1)}
          aria-describedby={errors?.addressLine1 ? "checkout-address-line1-error" : undefined}
        />
      </CheckoutField>

      <CheckoutField
        id="checkout-address-line2"
        label="Apartment / House No."
        className="sm:col-span-2"
      >
        <input
          id="checkout-address-line2"
          type="text"
          autoComplete="address-line2"
          value={value.addressLine2}
          onChange={(event) => update("addressLine2", event.target.value)}
          placeholder="Optional"
          className={checkoutInputClassName(false)}
        />
      </CheckoutField>

      <CheckoutField id="checkout-city" label="City" required error={errors?.city}>
        <input
          id="checkout-city"
          type="text"
          autoComplete="address-level2"
          value={value.city}
          onChange={(event) => update("city", event.target.value)}
          onBlur={() => onBlurField?.("city")}
          placeholder="Dar es Salaam"
          className={checkoutInputClassName(Boolean(errors?.city))}
          aria-invalid={Boolean(errors?.city)}
          aria-describedby={errors?.city ? "checkout-city-error" : undefined}
        />
      </CheckoutField>

      <CheckoutField id="checkout-region" label="Region" required error={errors?.region}>
        <SearchableRegionSelect
          id="checkout-region"
          value={value.region}
          onChange={(region) => update("region", region)}
          onBlur={() => onBlurField?.("region")}
          error={errors?.region}
        />
      </CheckoutField>

      <CheckoutField id="checkout-postal" label="Postal Code">
        <input
          id="checkout-postal"
          type="text"
          autoComplete="postal-code"
          value={value.postalCode}
          onChange={(event) => update("postalCode", event.target.value)}
          placeholder="Optional"
          className={checkoutInputClassName(false)}
        />
      </CheckoutField>

      <CheckoutField id="checkout-country" label="Country">
        <input
          id="checkout-country"
          type="text"
          autoComplete="country-name"
          value={value.country}
          className={`${checkoutInputClassName(false)} cursor-not-allowed text-zinc-500`}
          readOnly
          aria-readonly="true"
        />
      </CheckoutField>
    </div>
  );
}
