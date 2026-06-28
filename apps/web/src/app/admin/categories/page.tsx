import { AdminPlaceholderPage } from "@/components/admin/AdminPlaceholderPage";
import { AdminCategoriesPanel } from "@/components/admin/AdminCategoriesPanel";

export default function AdminCategoriesPage() {
  return (
    <>
      <AdminPlaceholderPage
        title="Categories"
        description="Browse categories with smart search and filters — product counts from your catalog."
        icon="🏷️"
      />
      <AdminCategoriesPanel />
    </>
  );
}
