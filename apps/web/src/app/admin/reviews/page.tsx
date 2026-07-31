import { AdminReviewsQueuePanel } from "@/components/admin/AdminReviewsQueuePanel";

export default function AdminReviewsPage() {
  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4">
        <h1 className="text-lg font-semibold text-zinc-900">Reviews</h1>
        <p className="mt-1 text-xs text-zinc-500">
          Moderate customer product reviews before they appear on the storefront.
        </p>
      </div>
      <AdminReviewsQueuePanel />
    </div>
  );
}
