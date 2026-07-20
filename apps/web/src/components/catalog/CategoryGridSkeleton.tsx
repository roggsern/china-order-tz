interface CategoryGridSkeletonProps {
  count?: number;
}

export function CategoryGridSkeleton({ count = 8 }: CategoryGridSkeletonProps) {
  return (
    <div
      className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      aria-hidden
    >
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="animate-pulse overflow-hidden rounded-2xl border border-zinc-200/80 bg-white"
        >
          <div className="h-40 bg-zinc-100" />
          <div className="space-y-3 p-5">
            <div className="h-4 w-2/3 rounded bg-zinc-100" />
            <div className="h-3 w-full rounded bg-zinc-100" />
            <div className="h-3 w-1/3 rounded bg-zinc-50" />
          </div>
        </div>
      ))}
    </div>
  );
}
