import { AdminStoreDashboard } from "@/components/admin/AdminStoreDashboard";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function AdminStoreDashboardPage({ params }: PageProps) {
  const { id } = await params;
  return <AdminStoreDashboard storeId={id} />;
}
