import { AdminStoresPanel } from "@/components/admin/AdminStoresPanel";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function AdminStoreDetailPage({ params }: PageProps) {
  const { id } = await params;
  return <AdminStoresPanel initialStoreId={id} />;
}
