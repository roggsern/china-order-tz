"use client";

interface SearchResultsSkeletonProps {
  rows?: number;
}

export function SearchResultsSkeleton({ rows = 4 }: SearchResultsSkeletonProps) {
  return (
    <div className="space-y-2 px-2 py-2" aria-hidden>
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="flex animate-pulse items-center gap-3 rounded-xl px-2 py-2.5">
          <div className="h-14 w-14 shrink-0 rounded-lg bg-zinc-100" />
          <div className="min-w-0 flex-1 space-y-2">
            <div className="h-3.5 w-3/4 rounded bg-zinc-100" />
            <div className="h-3 w-1/3 rounded bg-zinc-100" />
            <div className="h-2.5 w-1/4 rounded bg-zinc-50" />
          </div>
        </div>
      ))}
    </div>
  );
}
