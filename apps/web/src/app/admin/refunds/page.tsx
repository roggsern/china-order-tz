import { AdminRefundsPanel } from "@/components/admin/AdminRefundsPanel";

export default function AdminRefundsPage() {
  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4">
        <h1 className="text-lg font-semibold text-zinc-900">Refunds</h1>
        <p className="mt-1 text-xs text-zinc-500">
          Finance refund operations queue — review, approve, and process customer refunds.
        </p>
      </div>
      <AdminRefundsPanel />
    </div>
  );
}
