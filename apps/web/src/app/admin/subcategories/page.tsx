import { Suspense } from "react";
import { AdminSubcategoriesPanel } from "@/components/admin/AdminSubcategoriesPanel";

export default function AdminSubcategoriesPage() {
  return (
    <Suspense
      fallback={
        <div className="px-4 py-12 text-center text-sm text-zinc-500 sm:px-6 lg:px-8">
          Loading subcategories…
        </div>
      }
    >
      <AdminSubcategoriesPanel />
    </Suspense>
  );
}
