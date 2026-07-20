export function ProductFiltersSkeleton({ className = "" }: { className?: string }) {
  return (
    <aside className={`hidden animate-pulse space-y-6 lg:block ${className}`} aria-hidden>
      <div className="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <div className="h-4 w-20 rounded bg-zinc-100" />
        <div className="mt-5 space-y-4">
          <div className="h-20 rounded-xl bg-zinc-100" />
          <div className="h-20 rounded-xl bg-zinc-100" />
          <div className="h-32 rounded-xl bg-zinc-100" />
        </div>
      </div>
      <div className="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <div className="h-4 w-16 rounded bg-zinc-100" />
        <div className="mt-3 h-10 rounded-xl bg-zinc-100" />
      </div>
    </aside>
  );
}
