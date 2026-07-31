import { AdminRoleDetailPanel } from "@/components/admin/AdminRoleDetailPanel";

export default async function AdminSettingsRoleDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <AdminRoleDetailPanel roleId={id} />;
}
