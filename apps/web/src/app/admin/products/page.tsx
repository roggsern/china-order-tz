import { Suspense } from "react";
import { AdminCatalogProductsPanel } from "@/components/admin/AdminCatalogProductsPanel";

export default function AdminProductsPage() {
  return (
    <Suspense
      fallback={
        <div className="px-4 py-12 text-center text-sm text-zinc-500 sm:px-6 lg:px-8">
          Loading products…
        </div>
      }
    >
      <AdminCatalogProductsPanel />
    </Suspense>
  );
}
