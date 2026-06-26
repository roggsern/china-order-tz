import { AdminSidebar } from "@/components/admin/AdminSidebar";
import { AdminHeader } from "@/components/admin/AdminHeader";
import { AdminProductsProvider } from "@/components/admin/AdminProductsProvider";
import { AdminOrdersProvider } from "@/components/admin/AdminOrdersProvider";

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <AdminProductsProvider>
      <AdminOrdersProvider>
        <div className="flex min-h-screen flex-col bg-[#f6f6f7] lg:flex-row">
          <AdminSidebar />
          <div className="flex min-h-screen min-w-0 flex-1 flex-col">
            <AdminHeader />
            <main className="flex-1 overflow-auto">{children}</main>
          </div>
        </div>
      </AdminOrdersProvider>
    </AdminProductsProvider>
  );
}
