"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import Link from "next/link";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { SearchableRegionSelect } from "@/components/checkout/SearchableRegionSelect";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerAddressesApiError,
  createCustomerAddress,
  deleteCustomerAddress,
  fetchCustomerAddresses,
  setDefaultCustomerAddress,
  updateCustomerAddress,
  type CustomerAddress,
} from "@/lib/api/customer-addresses";
import {
  EMPTY_ADDRESS_BOOK_FORM,
  formatAddressLines,
  toCustomerAddressInput,
  validateAddressBookForm,
  type AddressBookFormValues,
} from "@/lib/account/customer-address-book";
import { normalizePhoneToE164 } from "@/lib/phone";

function addressToForm(address: CustomerAddress): AddressBookFormValues {
  return {
    label: address.label ?? "",
    recipient_name: address.recipient_name ?? "",
    phone: address.phone ?? "",
    street: address.street ?? address.address_line_1 ?? "",
    district: address.district ?? address.address_line_2 ?? "",
    city: address.city ?? "",
    region: address.region ?? "",
    country: address.country || "Tanzania",
    postal_code: address.postal_code ?? "",
    is_default: Boolean(address.is_default),
  };
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

export default function AccountAddressesPage() {
  const { isReady, isLoggedIn } = useCustomerSession();
  const [addresses, setAddresses] = useState<CustomerAddress[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<AddressBookFormValues>(EMPTY_ADDRESS_BOOK_FORM);
  const [formErrors, setFormErrors] = useState<
    Partial<Record<keyof AddressBookFormValues, string>>
  >({});

  const reload = useCallback(async () => {
    if (!getCustomerApiToken()) {
      setAddresses([]);
      setError("Sign in to manage your saved addresses.");
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const { addresses: rows } = await fetchCustomerAddresses();
      setAddresses(rows);
    } catch (err) {
      setAddresses([]);
      setError(
        err instanceof CustomerAddressesApiError
          ? err.message
          : "Unable to load saved addresses.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) return;
    void reload();
  }, [isReady, isLoggedIn, reload]);

  const openCreate = () => {
    setEditingId(null);
    setForm({ ...EMPTY_ADDRESS_BOOK_FORM, is_default: addresses.length === 0 });
    setFormErrors({});
    setShowForm(true);
    setMessage(null);
  };

  const openEdit = (address: CustomerAddress) => {
    setEditingId(address.id);
    setForm(addressToForm(address));
    setFormErrors({});
    setShowForm(true);
    setMessage(null);
  };

  const closeForm = () => {
    setShowForm(false);
    setEditingId(null);
    setForm(EMPTY_ADDRESS_BOOK_FORM);
    setFormErrors({});
  };

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    const errors = validateAddressBookForm(form);
    setFormErrors(errors);
    if (Object.keys(errors).length > 0) return;

    setBusy(true);
    setError(null);
    setMessage(null);

    const payload = toCustomerAddressInput({
      ...form,
      phone: normalizePhoneToE164(form.phone) ?? form.phone.trim(),
    });

    try {
      if (editingId) {
        await updateCustomerAddress(editingId, payload);
        setMessage("Address updated.");
      } else {
        await createCustomerAddress(payload);
        setMessage("Address saved.");
      }
      closeForm();
      await reload();
    } catch (err) {
      setError(
        err instanceof CustomerAddressesApiError ? err.message : "Unable to save address.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onDelete = async (address: CustomerAddress) => {
    if (!window.confirm("Delete this saved address?")) return;
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await deleteCustomerAddress(address.id);
      setMessage("Address deleted.");
      if (editingId === address.id) closeForm();
      await reload();
    } catch (err) {
      setError(
        err instanceof CustomerAddressesApiError ? err.message : "Unable to delete address.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onSetDefault = async (address: CustomerAddress) => {
    if (address.is_default) return;
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await setDefaultCustomerAddress(address.id);
      setMessage("Default address updated.");
      await reload();
    } catch (err) {
      setError(
        err instanceof CustomerAddressesApiError
          ? err.message
          : "Unable to set default address.",
      );
    } finally {
      setBusy(false);
    }
  };

  const updateField = <K extends keyof AddressBookFormValues>(
    field: K,
    value: AddressBookFormValues[K],
  ) => {
    setForm((current) => ({ ...current, [field]: value }));
    setFormErrors((current) => ({ ...current, [field]: undefined }));
  };

  return (
    <StorefrontShell>
      <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <nav aria-label="Breadcrumb" className="mb-6">
          <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <li>
              <Link href="/account" className="font-medium transition hover:text-[#8b6914]">
                My Account
              </Link>
            </li>
            <li aria-hidden>/</li>
            <li className="font-semibold text-zinc-900">Saved Addresses</li>
          </ol>
        </nav>

        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-zinc-900">Address Book</h1>
            <p className="mt-1 text-sm text-zinc-500">
              Save delivery addresses for faster checkout. Orders still snapshot the address at
              purchase time.
            </p>
          </div>
          {!showForm ? (
            <button
              type="button"
              onClick={openCreate}
              disabled={busy || !isLoggedIn}
              className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#6f5410] disabled:opacity-50"
            >
              Add address
            </button>
          ) : null}
        </div>

        {!isReady || loading ? <AccountPageSkeleton /> : null}

        {error ? (
          <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {error}
          </div>
        ) : null}
        {message ? (
          <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {message}
          </div>
        ) : null}

        {showForm ? (
          <form
            onSubmit={onSubmit}
            className="mb-8 space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm"
          >
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold text-zinc-900">
                {editingId ? "Edit address" : "New address"}
              </h2>
              <button
                type="button"
                onClick={closeForm}
                className="text-sm font-medium text-zinc-500 hover:text-zinc-800"
              >
                Cancel
              </button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block text-sm sm:col-span-2">
                <span className="font-medium text-zinc-700">Label (optional)</span>
                <input
                  value={form.label}
                  onChange={(e) => updateField("label", e.target.value)}
                  placeholder="Home, Work…"
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                />
              </label>

              <label className="block text-sm">
                <span className="font-medium text-zinc-700">Recipient name</span>
                <input
                  value={form.recipient_name}
                  onChange={(e) => updateField("recipient_name", e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
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
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                  required
                />
                <FieldError message={formErrors.phone} />
              </label>

              <label className="block text-sm sm:col-span-2">
                <span className="font-medium text-zinc-700">Street / address details</span>
                <input
                  value={form.street}
                  onChange={(e) => updateField("street", e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                  required
                />
                <FieldError message={formErrors.street} />
              </label>

              <label className="block text-sm">
                <span className="font-medium text-zinc-700">District</span>
                <input
                  value={form.district}
                  onChange={(e) => updateField("district", e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                  required
                />
                <FieldError message={formErrors.district} />
              </label>

              <label className="block text-sm">
                <span className="font-medium text-zinc-700">City</span>
                <input
                  value={form.city}
                  onChange={(e) => updateField("city", e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                  required
                />
                <FieldError message={formErrors.city} />
              </label>

              <div className="block text-sm">
                <span className="font-medium text-zinc-700">Region</span>
                <div className="mt-1">
                  <SearchableRegionSelect
                    id="address-book-region"
                    value={form.region}
                    onChange={(region) => updateField("region", region)}
                    error={formErrors.region}
                  />
                </div>
                <FieldError message={formErrors.region} />
              </div>

              <label className="block text-sm">
                <span className="font-medium text-zinc-700">Postal code (optional)</span>
                <input
                  value={form.postal_code}
                  onChange={(e) => updateField("postal_code", e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2"
                />
              </label>

              <label className="flex items-center gap-2 text-sm sm:col-span-2">
                <input
                  type="checkbox"
                  checked={form.is_default}
                  onChange={(e) => updateField("is_default", e.target.checked)}
                />
                <span className="text-zinc-700">Set as default delivery address</span>
              </label>
            </div>

            <button
              type="submit"
              disabled={busy}
              className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#6f5410] disabled:opacity-50"
            >
              {editingId ? "Save changes" : "Save address"}
            </button>
          </form>
        ) : null}

        {!loading && isReady && addresses.length === 0 && !showForm ? (
          <div className="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-10 text-center">
            <p className="text-sm font-medium text-zinc-800">No saved addresses yet</p>
            <p className="mt-1 text-sm text-zinc-500">
              Add an address to reuse it at checkout.
            </p>
            <button
              type="button"
              onClick={openCreate}
              className="mt-4 rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white"
            >
              Add your first address
            </button>
          </div>
        ) : null}

        <ul className="space-y-3">
          {addresses.map((address) => (
            <li
              key={address.id}
              className="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-semibold text-zinc-900">
                      {address.label?.trim() || address.recipient_name}
                    </p>
                    {address.is_default ? (
                      <span className="rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-xs font-semibold text-[#8b6914]">
                        Default
                      </span>
                    ) : null}
                  </div>
                  <p className="mt-1 text-sm text-zinc-600">{address.recipient_name}</p>
                  <p className="text-sm text-zinc-600">{address.phone}</p>
                  <p className="mt-2 text-sm leading-relaxed text-zinc-700">
                    {formatAddressLines(address)}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  {!address.is_default ? (
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void onSetDefault(address)}
                      className="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 disabled:opacity-50"
                    >
                      Set default
                    </button>
                  ) : null}
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => openEdit(address)}
                    className="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 disabled:opacity-50"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void onDelete(address)}
                    className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50"
                  >
                    Delete
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </StorefrontShell>
  );
}
