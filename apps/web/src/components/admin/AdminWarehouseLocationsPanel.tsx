"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminWarehouseOpsApiError,
  canViewWarehouse,
  fetchWarehouseBins,
  fetchWarehouseFacilities,
  fetchWarehouseZones,
  type WarehouseBin,
  type WarehouseFacility,
  type WarehouseZone,
} from "@/lib/api/admin-warehouse-operations";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

export function AdminWarehouseLocationsPanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewWarehouse(permissions);

  const [facilities, setFacilities] = useState<WarehouseFacility[]>([]);
  const [zones, setZones] = useState<WarehouseZone[]>([]);
  const [bins, setBins] = useState<WarehouseBin[]>([]);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!canView) return;
    try {
      const [f, z, b] = await Promise.all([
        fetchWarehouseFacilities(),
        fetchWarehouseZones(),
        fetchWarehouseBins(),
      ]);
      setFacilities(f);
      setZones(z);
      setBins(b);
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Unable to load locations.");
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  if (!canView) {
    return <div className="p-6 text-sm text-zinc-600">You do not have permission to view locations.</div>;
  }

  return (
    <div className="grid gap-4 p-4 lg:grid-cols-3 lg:p-8">
      {error ? <p className="col-span-full text-sm text-red-700">{error}</p> : null}
      <section className="admin-card p-4">
        <h2 className="text-sm font-semibold">Warehouses</h2>
        <ul className="mt-3 space-y-2 text-sm">
          {facilities.map((f) => (
            <li key={f.id}>
              <span className="font-medium">{f.name}</span>
              <span className="block text-xs text-zinc-500">{f.code}{f.inventory_warehouse_code ? ` · ${f.inventory_warehouse_code}` : ""}</span>
            </li>
          ))}
        </ul>
      </section>
      <section className="admin-card p-4">
        <h2 className="text-sm font-semibold">Zones</h2>
        <ul className="mt-3 space-y-2 text-sm">
          {zones.map((z) => (
            <li key={z.id}>{z.name} <span className="text-xs text-zinc-500">({z.code})</span></li>
          ))}
        </ul>
      </section>
      <section className="admin-card p-4">
        <h2 className="text-sm font-semibold">Bins</h2>
        <ul className="mt-3 space-y-2 text-sm">
          {bins.map((b) => (
            <li key={b.id}>{b.name} <span className="text-xs text-zinc-500">({b.code})</span></li>
          ))}
        </ul>
      </section>
    </div>
  );
}
