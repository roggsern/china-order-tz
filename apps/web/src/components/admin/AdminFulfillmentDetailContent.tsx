"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminFulfillmentApiError,
  fetchAdminFulfillmentOperational,
} from "@/lib/api/admin-fulfillments";
import { AdminFulfillmentOperationalWorkspace } from "@/components/admin/AdminFulfillmentOperationalWorkspace";
import { parseFulfillmentOperationalModel } from "@/lib/admin/fulfillment-operational";

interface AdminFulfillmentDetailContentProps {
  fulfillmentId: string;
}

export function AdminFulfillmentDetailContent({
  fulfillmentId,
}: AdminFulfillmentDetailContentProps) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [model, setModel] = useState<ReturnType<typeof parseFulfillmentOperationalModel>>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const payload = await fetchAdminFulfillmentOperational(fulfillmentId);
      const parsed = parseFulfillmentOperationalModel(payload);
      if (!parsed) {
        throw new AdminFulfillmentApiError("Invalid operational fulfilment payload.");
      }
      setModel(parsed);
    } catch (err) {
      setModel(null);
      setError(
        err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to load fulfilment workspace.",
      );
    } finally {
      setLoading(false);
    }
  }, [fulfillmentId]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <div className="admin-card p-10 text-center text-sm text-zinc-500">
          Loading fulfilment workspace…
        </div>
      </div>
    );
  }

  if (error || !model) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <div className="admin-card p-6">
          <p className="text-sm text-red-700" role="alert">
            {error ?? "Fulfilment not found."}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <AdminFulfillmentOperationalWorkspace model={model} onRefresh={load} />
    </div>
  );
}
