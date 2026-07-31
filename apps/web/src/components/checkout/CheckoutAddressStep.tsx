"use client";

import { useState, type FormEvent } from "react";
import Link from "next/link";
import { SearchableRegionSelect } from "@/components/checkout/SearchableRegionSelect";
import type { CustomerAddress } from "@/lib/api/customer-addresses";
import {
  EMPTY_ADDRESS_BOOK_FORM,
  formatAddressLines,
  toCustomerAddressInput,
  validateAddressBookForm,
  type AddressBookFormValues,
} from "@/lib/account/customer-address-book";
import { normalizePhoneToE164 } from "@/lib/phone";

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

type CheckoutAddressStepProps = {
  addresses: CustomerAddress[];
  selectedAddressId: string | null;
  onSelectAddress: (addressId: string) => void;
  showAddForm: boolean;
  onShowAddForm: (show: boolean) => void;
  onSaveNewAddress: (input: ReturnType<typeof toCustomerAddressInput>) => Promise<void>;
  isLoading?: boolean;
  isSaving?: boolean;
  error?: string;
  profileDefaults?: Pick<AddressBookFormValues, "recipient_name" | "phone">;
};

export function CheckoutAddressStep({
  addresses,
  selectedAddressId,
  onSelectAddress,
  showAddForm,
  onShowAddForm,
  onSaveNewAddress,
  isLoading = false,
  isSaving = false,
  error,
  profileDefaults,
}: CheckoutAddressStepProps) {
  const [form, setForm] = useState<AddressBookFormValues>(() => ({
    ...EMPTY_ADDRESS_BOOK_FORM,
    recipient_name: profileDefaults?.recipient_name ?? "",
    phone: profileDefaults?.phone ?? "",
    is_default: addresses.length === 0,
  }));
  const [formErrors, setFormErrors] = useState<
    Partial<Record<keyof AddressBookFormValues, string>>
  >({});
  const [saveError, setSaveError] = useState<string | undefined>();

  const updateField = <K extends keyof AddressBookFormValues>(
    key: K,
    value: AddressBookFormValues[K],
  ) => {
    setForm((prev) => ({ ...prev, [key]: value }));
    setFormErrors((prev) => ({ ...prev, [key]: undefined }));
    setSaveError(undefined);
  };

  const handleSubmitNew = async (event: FormEvent) => {
    event.preventDefault();
    const errors = validateAddressBookForm(form);
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    setSaveError(undefined);
    try {
      const input = toCustomerAddressInput({
        ...form,
        phone: normalizePhoneToE164(form.phone) ?? form.phone.trim(),
        is_default: true,
      });
      await onSaveNewAddress(input);
      setForm({
        ...EMPTY_ADDRESS_BOOK_FORM,
        recipient_name: profileDefaults?.recipient_name ?? "",
        phone: profileDefaults?.phone ?? "",
        is_default: false,
      });
      onShowAddForm(false);
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : "Unable to save address.");
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-3" aria-busy="true" aria-label="Loading saved addresses">
        {[1, 2].map((key) => (
          <div key={key} className="h-20 animate-pulse rounded-2xl bg-zinc-100" />
        ))}
      </div>
    );
  }

  const showInlineForm = showAddForm || addresses.length === 0;

  return (
    <div className="space-y-4">
      {error ? (
        <p role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </p>
      ) : null}

      {addresses.length > 0 && !showInlineForm ? (
        <ul className="space-y-3" role="radiogroup" aria-label="Saved delivery addresses">
          {addresses.map((address) => {
            const selected = address.id === selectedAddressId;
            return (
              <li key={address.id}>
                <label
                  className={`flex cursor-pointer gap-3 rounded-2xl border p-4 transition ${
                    selected
                      ? "border-[#c9a227] bg-[#c9a227]/5 ring-1 ring-[#c9a227]/40"
                      : "border-zinc-200 bg-white hover:border-zinc-300"
                  }`}
                >
                  <input
                    type="radio"
                    name="checkout-saved-address"
                    className="mt-1 h-4 w-4 shrink-0 accent-[#c9a227]"
                    checked={selected}
                    onChange={() => onSelectAddress(address.id)}
                  />
                  <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="font-semibold text-zinc-900">
                        {address.label?.trim() || address.recipient_name}
                      </span>
                      {address.is_default ? (
                        <span className="rounded-full bg-zinc-900 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                          Default
                        </span>
                      ) : null}
                    </span>
                    <span className="mt-1 block text-sm text-zinc-600">{address.recipient_name}</span>
                    <span className="block text-sm text-zinc-600">{address.phone}</span>
                    <span className="mt-1 block text-sm text-zinc-500">{formatAddressLines(address)}</span>
                  </span>
                </label>
              </li>
            );
          })}
        </ul>
      ) : null}

      {addresses.length > 0 && !showInlineForm ? (
        <button
          type="button"
          onClick={() => onShowAddForm(true)}
          className="text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
        >
          + Add a new address
        </button>
      ) : null}

      {showInlineForm ? (
        <form onSubmit={(event) => void handleSubmitNew(event)} className="space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50/80 p-4">
          <div className="flex items-center justify-between gap-3">
            <h3 className="text-sm font-semibold text-zinc-900">
              {addresses.length === 0 ? "Add your delivery address" : "New delivery address"}
            </h3>
            {addresses.length > 0 ? (
              <button
                type="button"
                onClick={() => onShowAddForm(false)}
                className="text-sm font-medium text-zinc-500 hover:text-zinc-800"
              >
                Cancel
              </button>
            ) : null}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block text-sm sm:col-span-2">
              <span className="font-medium text-zinc-700">Label (optional)</span>
              <input
                value={form.label}
                onChange={(e) => updateField("label", e.target.value)}
                placeholder="Home, Work…"
                className="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2"
              />
            </label>

            <label className="block text-sm">
              <span className="font-medium text-zinc-700">Recipient name</span>
              <input
                value={form.recipient_name}
                onChange={(e) => updateField("recipient_name", e.target.value)}
                className="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2"
                required
              />
              <FieldError message={formErrors.recipient_name} />
            </label>

            <label className="block text-sm">
              <span className="font-medium text-zinc-700">Phone</span>
              <input
                value={form.phone}
                onChange={(e) => updateField("phone", e.target.value)}
                placeholder="+2557…"
                className="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2"
                required
              />
              <FieldError message={formErrors.phone} />
            </label>

            <label className="block text-sm sm:col-span-2">
              <span className="font-medium text-zinc-700">Street / address details</span>
              <input
                value={form.street}
                onChange={(e) => updateField("street", e.target.value)}
                className="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2"
                required
              />
              <FieldError message={formErrors.street} />
            </label>

            <label className="block text-sm">
              <span className="font-medium text-zinc-700">District</span>
              <input
                value={form.district}
                onChange={(e) => updateField("district", e.target.value)}
                className="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2"
                required
              />
              <FieldError message={formErrors.district} />
            </label>

            <label className="block text-sm">
              <span className="font-medium text-zinc-700">City</span>
              <input
                value={form.city}
                onChange={(e) => updateField("city", e.target.value)}
                className="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2"
                required
              />
              <FieldError message={formErrors.city} />
            </label>

            <label className="block text-sm sm:col-span-2">
              <span className="font-medium text-zinc-700">Region</span>
              <SearchableRegionSelect
                id="checkout-new-address-region"
                value={form.region}
                onChange={(region) => updateField("region", region)}
                error={formErrors.region}
              />
            </label>
          </div>

          {saveError ? (
            <p role="alert" className="text-sm text-red-600">
              {saveError}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={isSaving}
            className="inline-flex min-h-11 items-center justify-center rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#c9a227] hover:text-zinc-900 disabled:opacity-60"
          >
            {isSaving ? "Saving address…" : "Save and use this address"}
          </button>
        </form>
      ) : null}

      <p className="text-xs text-zinc-500">
        Manage all saved addresses in{" "}
        <Link href="/account/addresses" className="font-semibold text-[#8b6914] hover:text-[#c9a227]">
          your account
        </Link>
        .
      </p>
    </div>
  );
}
