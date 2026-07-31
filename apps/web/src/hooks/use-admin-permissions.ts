"use client";

import { useEffect, useState } from "react";
import { fetchAdminMe, resolveAdminPermissions } from "@/lib/api/admin-me";

export function useAdminPermissions(): {
  permissions: string[] | undefined;
  adminId: string | null;
  loading: boolean;
} {
  const [permissions, setPermissions] = useState<string[] | undefined>(undefined);
  const [adminId, setAdminId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    void fetchAdminMe()
      .then((admin) => {
        if (cancelled) {
          return;
        }
        setAdminId(admin.id);
        setPermissions(resolveAdminPermissions(admin));
      })
      .catch(() => {
        if (!cancelled) {
          setAdminId(null);
          setPermissions(undefined);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return { permissions, adminId, loading };
}
