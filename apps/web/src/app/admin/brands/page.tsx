import { AdminPlaceholderPage } from "@/components/admin/AdminPlaceholderPage";
import { buyFromTzBrandMenu } from "@/lib/catalog/brands";

export default function AdminBrandsPage() {
  return (
    <>
      <AdminPlaceholderPage
        title="Brands"
        description="Manage Buy From TZ brands and China supplier brands."
        icon="✨"
      />
      <div className="px-4 pb-8 sm:px-6 lg:px-8">
        <div className="admin-card overflow-hidden">
          <div className="border-b border-zinc-200 px-5 py-4">
            <h2 className="text-sm font-semibold text-zinc-900">Current brands (read-only)</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Static seed data — brand CRUD will connect to the API later.
            </p>
          </div>
          <div className="divide-y divide-zinc-100">
            {buyFromTzBrandMenu.map((brand) => (
              <div key={brand.slug} className="flex items-center gap-4 px-5 py-3">
                <span className="text-2xl">{brand.icon}</span>
                <div>
                  <p className="text-sm font-medium text-zinc-900">{brand.name}</p>
                  <p className="text-xs text-zinc-500">
                    {brand.slug} · {brand.subcategories.length} subcategories
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
}
