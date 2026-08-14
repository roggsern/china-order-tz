import { Suspense } from "react";
import { AdminCategoriesPanel } from "@/components/admin/AdminCategoriesPanel";

export default function AdminCategoriesPage() {
  return (
    <Suspense
      fallback={
        <div className="px-4 py-12 text-center text-sm text-zinc-500 sm:px-6 lg:px-8">
          Loading categories…
        </div>
      }
    >
      <AdminCategoriesPanel />
    </Suspense>
  );
}
