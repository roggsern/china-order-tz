export function AnalyticsSkeleton() {
  return (
    <div className="space-y-8" aria-busy="true" aria-label="Loading analytics">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {Array.from({ length: 6 }).map((_, index) => (
          <div key={index} className="admin-stat-card admin-stat-card-dark h-28 animate-pulse bg-zinc-900/40" />
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <div className="admin-card h-80 animate-pulse bg-zinc-100/80" />
        <div className="admin-card h-80 animate-pulse bg-zinc-100/80" />
      </div>

      <div className="admin-card h-96 animate-pulse bg-zinc-100/80" />
    </div>
  );
}
