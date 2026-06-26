import { AdminPlaceholderPage } from "@/components/admin/AdminPlaceholderPage";
import { categories } from "@/lib/catalog/categories";

export default function AdminCategoriesPage() {
  return (
    <>
      <AdminPlaceholderPage
        title="Categories"
        description="Manage product categories and subcategories."
        icon="🏷️"
      />
      <div className="px-4 pb-8 sm:px-6 lg:px-8">
        <div className="admin-card overflow-hidden">
          <div className="border-b border-zinc-200 px-5 py-4">
            <h2 className="text-sm font-semibold text-zinc-900">Current categories (read-only)</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Static seed data — editable category management will connect to the API later.
            </p>
          </div>
          <div className="divide-y divide-zinc-100">
            {categories.map((category) => (
              <div key={category.slug} className="flex items-center gap-4 px-5 py-3">
                <span className="text-2xl">{category.icon}</span>
                <div>
                  <p className="text-sm font-medium text-zinc-900">{category.name}</p>
                  <p className="text-xs text-zinc-500">{category.slug}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
}
