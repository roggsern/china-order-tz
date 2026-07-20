"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminProcurementApiError,
  createAdminSupplier,
  fetchAdminSuppliers,
  updateAdminSupplier,
  upsertAdminSupplierProduct,
  type AdminSupplier,
} from "@/lib/api/admin-procurement";

export function AdminSuppliersPanel() {
  const [rows, setRows] = useState<AdminSupplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: "",
    code: "",
    country: "China",
    contact_person: "",
    email: "",
    phone: "",
    payment_terms: "Net 30",
  });
  const [mapForm, setMapForm] = useState({
    supplierId: "",
    product_variant_id: "",
    purchase_cost: "",
    supplier_sku: "",
    lead_time_days: "",
  });

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setRows(await fetchAdminSuppliers());
    } catch (err) {
      setRows([]);
      setError(err instanceof AdminProcurementApiError ? err.message : "Unable to load suppliers.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const onCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await createAdminSupplier({
        name: form.name.trim(),
        code: form.code.trim() || undefined,
        country: form.country.trim() || "China",
        contact_person: form.contact_person.trim() || null,
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
        payment_terms: form.payment_terms.trim() || null,
      });
      setForm({
        name: "",
        code: "",
        country: "China",
        contact_person: "",
        email: "",
        phone: "",
        payment_terms: "Net 30",
      });
      await reload();
    } catch (err) {
      setError(err instanceof AdminProcurementApiError ? err.message : "Unable to create supplier.");
    } finally {
      setBusy(false);
    }
  };

  const toggleActive = async (supplier: AdminSupplier) => {
    setBusy(true);
    setError(null);
    try {
      await updateAdminSupplier(supplier.id, { is_active: !supplier.is_active });
      await reload();
    } catch (err) {
      setError(err instanceof AdminProcurementApiError ? err.message : "Unable to update supplier.");
    } finally {
      setBusy(false);
    }
  };

  const onMapProduct = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!mapForm.supplierId) return;
    setBusy(true);
    setError(null);
    try {
      await upsertAdminSupplierProduct(mapForm.supplierId, {
        product_variant_id: mapForm.product_variant_id.trim(),
        purchase_cost: Number(mapForm.purchase_cost),
        supplier_sku: mapForm.supplier_sku.trim() || null,
        lead_time_days: mapForm.lead_time_days ? Number(mapForm.lead_time_days) : null,
        currency: "TZS",
      });
      setMapForm({
        supplierId: mapForm.supplierId,
        product_variant_id: "",
        purchase_cost: "",
        supplier_sku: "",
        lead_time_days: "",
      });
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminProcurementApiError ? err.message : "Unable to map supplier product.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-900">Supplier Management</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Manage suppliers and variant purchase mappings for procurement.
        </p>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <section className="admin-card p-5">
        <h2 className="text-sm font-semibold text-zinc-900">Create supplier</h2>
        <form onSubmit={(e) => void onCreate(e)} className="mt-4 grid gap-3 sm:grid-cols-2">
          <input
            className="admin-input"
            placeholder="Name *"
            value={form.name}
            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            required
          />
          <input
            className="admin-input"
            placeholder="Code (optional)"
            value={form.code}
            onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
          />
          <input
            className="admin-input"
            placeholder="Country"
            value={form.country}
            onChange={(e) => setForm((f) => ({ ...f, country: e.target.value }))}
          />
          <input
            className="admin-input"
            placeholder="Contact person"
            value={form.contact_person}
            onChange={(e) => setForm((f) => ({ ...f, contact_person: e.target.value }))}
          />
          <input
            className="admin-input"
            placeholder="Email"
            value={form.email}
            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
          />
          <input
            className="admin-input"
            placeholder="Phone"
            value={form.phone}
            onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
          />
          <input
            className="admin-input sm:col-span-2"
            placeholder="Payment terms"
            value={form.payment_terms}
            onChange={(e) => setForm((f) => ({ ...f, payment_terms: e.target.value }))}
          />
          <button type="submit" disabled={busy} className="admin-btn-primary sm:col-span-2">
            {busy ? "Saving…" : "Create supplier"}
          </button>
        </form>
      </section>

      <section className="admin-card p-5">
        <h2 className="text-sm font-semibold text-zinc-900">Map supplier product (variant)</h2>
        <form onSubmit={(e) => void onMapProduct(e)} className="mt-4 grid gap-3 sm:grid-cols-2">
          <select
            className="admin-input"
            value={mapForm.supplierId}
            onChange={(e) => setMapForm((f) => ({ ...f, supplierId: e.target.value }))}
            required
          >
            <option value="">Select supplier</option>
            {rows.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name} ({s.code})
              </option>
            ))}
          </select>
          <input
            className="admin-input"
            placeholder="Product variant UUID *"
            value={mapForm.product_variant_id}
            onChange={(e) => setMapForm((f) => ({ ...f, product_variant_id: e.target.value }))}
            required
          />
          <input
            className="admin-input"
            placeholder="Purchase cost *"
            type="number"
            min={0}
            step="0.01"
            value={mapForm.purchase_cost}
            onChange={(e) => setMapForm((f) => ({ ...f, purchase_cost: e.target.value }))}
            required
          />
          <input
            className="admin-input"
            placeholder="Supplier SKU"
            value={mapForm.supplier_sku}
            onChange={(e) => setMapForm((f) => ({ ...f, supplier_sku: e.target.value }))}
          />
          <input
            className="admin-input"
            placeholder="Lead time (days)"
            type="number"
            min={0}
            value={mapForm.lead_time_days}
            onChange={(e) => setMapForm((f) => ({ ...f, lead_time_days: e.target.value }))}
          />
          <button type="submit" disabled={busy} className="admin-btn-primary">
            Save mapping
          </button>
        </form>
      </section>

      <section className="admin-card overflow-hidden">
        <div className="border-b border-zinc-100 px-5 py-3">
          <h2 className="text-sm font-semibold text-zinc-900">Suppliers</h2>
        </div>
        {loading ? (
          <p className="p-5 text-sm text-zinc-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-5 text-sm text-zinc-500">No suppliers yet.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Code</th>
                  <th className="px-4 py-3">Country</th>
                  <th className="px-4 py-3">Contact</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {rows.map((supplier) => (
                  <tr key={supplier.id}>
                    <td className="px-4 py-3 font-medium text-zinc-900">{supplier.name}</td>
                    <td className="px-4 py-3 font-mono text-xs text-zinc-600">{supplier.code}</td>
                    <td className="px-4 py-3 text-zinc-600">{supplier.country ?? "—"}</td>
                    <td className="px-4 py-3 text-zinc-600">
                      {supplier.contact_person || supplier.email || "—"}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                          supplier.is_active
                            ? "bg-green-50 text-green-800"
                            : "bg-zinc-100 text-zinc-600"
                        }`}
                      >
                        {supplier.is_active ? "Active" : "Inactive"}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => void toggleActive(supplier)}
                        className="text-xs font-semibold text-[#8a7020] hover:underline"
                      >
                        {supplier.is_active ? "Deactivate" : "Activate"}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}
