"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminWarehouseQueuePanel } from "@/components/admin/AdminWarehouseQueuePanel";
import { AdminWarehousePickListsPanel } from "@/components/admin/AdminWarehousePickListsPanel";
import { AdminWarehousePackingPanel } from "@/components/admin/AdminWarehousePackingPanel";
import { AdminWarehouseTransfersPanel } from "@/components/admin/AdminWarehouseTransfersPanel";
import { AdminWarehouseLocationsPanel } from "@/components/admin/AdminWarehouseLocationsPanel";
import {
  buildWarehouseOperationalMaps,
  type WarehouseOperationalMaps,
} from "@/lib/api/admin-warehouse-job-operations";
import {
  fetchWarehousePacking,
  fetchWarehousePickLists,
  type WarehousePackingRecord,
  type WarehousePickList,
} from "@/lib/api/admin-warehouse-operations";

const TABS = [
  { id: "jobs", label: "Jobs queue" },
  { id: "picks", label: "Pick lists" },
  { id: "packing", label: "Packing queue" },
  { id: "transfers", label: "Transfers" },
  { id: "locations", label: "Locations" },
] as const;

type TabId = (typeof TABS)[number]["id"];

export function AdminWarehouseOperationsPanel() {
  const [tab, setTab] = useState<TabId>("jobs");
  const [operationalMaps, setOperationalMaps] = useState<WarehouseOperationalMaps>({
    pickListByJobId: {},
    packingByJobId: {},
  });
  const [focusedPickListId, setFocusedPickListId] = useState<string | null>(null);
  const [focusedPackingId, setFocusedPackingId] = useState<string | null>(null);

  const reloadOperationalMaps = useCallback(async () => {
    const [pickLists, packingRecords] = await Promise.all([
      fetchWarehousePickLists(),
      fetchWarehousePacking(),
    ]);
    setOperationalMaps(buildWarehouseOperationalMaps(pickLists, packingRecords));
  }, []);

  useEffect(() => {
    void reloadOperationalMaps();
  }, [reloadOperationalMaps]);

  const handlePickListCreated = (pickList: WarehousePickList) => {
    setOperationalMaps((prev) => ({
      ...prev,
      pickListByJobId: { ...prev.pickListByJobId, [pickList.warehouse_job_id]: pickList.id },
    }));
    setFocusedPickListId(pickList.id);
    setTab("picks");
  };

  const handleOpenPickList = (pickListId: string) => {
    setFocusedPickListId(pickListId);
    setTab("picks");
  };

  const handlePackingCreated = (record: WarehousePackingRecord) => {
    setOperationalMaps((prev) => ({
      ...prev,
      packingByJobId: { ...prev.packingByJobId, [record.warehouse_job_id]: record.id },
    }));
    setFocusedPackingId(record.id);
    setTab("packing");
  };

  const handleOpenPacking = (packingId: string) => {
    setFocusedPackingId(packingId);
    setTab("packing");
  };

  return (
    <div>
      <div className="px-4 pt-6 sm:px-6 lg:px-8">
        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">Operations</p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-zinc-900">Warehouse</h1>
        <p className="mt-1 text-sm text-zinc-500">Jobs, pick lists, packing, transfers, and locations.</p>
      </div>
      <div className="border-b border-zinc-200 px-4 sm:px-6 lg:px-8">
        <nav className="-mb-px flex gap-4 overflow-x-auto">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setTab(item.id)}
              className={`whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold ${
                tab === item.id
                  ? "border-[#8b6914] text-[#8b6914]"
                  : "border-transparent text-zinc-500 hover:text-zinc-800"
              }`}
            >
              {item.label}
            </button>
          ))}
        </nav>
      </div>

      {tab === "jobs" ? (
        <AdminWarehouseQueuePanel
          embedded
          operationalMaps={operationalMaps}
          onOperationalMapsChange={reloadOperationalMaps}
          onPickListCreated={handlePickListCreated}
          onOpenPickList={handleOpenPickList}
          onPackingCreated={handlePackingCreated}
          onOpenPacking={handleOpenPacking}
        />
      ) : null}
      {tab === "picks" ? (
        <AdminWarehousePickListsPanel
          initialSelectedId={focusedPickListId}
          onMapsChange={reloadOperationalMaps}
        />
      ) : null}
      {tab === "packing" ? (
        <AdminWarehousePackingPanel
          initialSelectedId={focusedPackingId}
          onMapsChange={reloadOperationalMaps}
        />
      ) : null}
      {tab === "transfers" ? <AdminWarehouseTransfersPanel /> : null}
      {tab === "locations" ? <AdminWarehouseLocationsPanel /> : null}
    </div>
  );
}
