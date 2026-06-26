import { AdminOrderDetailContent } from "@/components/admin/AdminOrderDetailContent";

interface AdminOrderDetailPageProps {
  params: Promise<{ orderId: string }>;
}

export default async function AdminOrderDetailPage({ params }: AdminOrderDetailPageProps) {
  const { orderId } = await params;
  return <AdminOrderDetailContent orderId={orderId} />;
}
