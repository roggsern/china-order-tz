import { AdminRefundPendingQueuePanel } from "@/components/admin/AdminRefundPendingQueuePanel";
import { AdminReturnsQueuePanel } from "@/components/admin/AdminReturnsQueuePanel";

export default function AdminReturnsPage() {
  return (
    <div className="space-y-6">
      <AdminRefundPendingQueuePanel />
      <AdminReturnsQueuePanel />
    </div>
  );
}
