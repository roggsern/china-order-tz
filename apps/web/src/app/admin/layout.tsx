import { AdminProductsProvider } from "@/components/admin/AdminProductsProvider";
import { AdminOrdersProvider } from "@/components/admin/AdminOrdersProvider";
import { AdminAuthProvider } from "@/components/admin/AdminAuthProvider";
import { AdminShell } from "@/components/admin/AdminShell";

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <AdminAuthProvider>
      <AdminProductsProvider>
        <AdminOrdersProvider>
          <AdminShell>{children}</AdminShell>
        </AdminOrdersProvider>
      </AdminProductsProvider>
    </AdminAuthProvider>
  );
}
