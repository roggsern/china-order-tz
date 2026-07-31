import { AdminFulfillmentDetailContent } from "@/components/admin/AdminFulfillmentDetailContent";

interface AdminFulfillmentDetailPageProps {
  params: Promise<{ fulfillmentId: string }>;
}

export default async function AdminFulfillmentDetailPage({
  params,
}: AdminFulfillmentDetailPageProps) {
  const { fulfillmentId } = await params;
  return <AdminFulfillmentDetailContent fulfillmentId={fulfillmentId} />;
}
