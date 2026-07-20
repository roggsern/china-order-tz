import { AdminCustomerDetailPanel } from "@/components/admin/AdminCustomerDetailPanel";

type Props = { params: Promise<{ id: string }> };

export default async function AdminCustomerDetailPage({ params }: Props) {
  const { id } = await params;
  return <AdminCustomerDetailPanel customerId={id} />;
}
