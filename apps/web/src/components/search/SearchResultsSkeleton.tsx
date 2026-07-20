"use client";

import { Skeleton } from "@/components/ui/Skeleton";

interface SearchResultsSkeletonProps {
  rows?: number;
}

export function SearchResultsSkeleton({ rows = 4 }: SearchResultsSkeletonProps) {
  return (
    <div className="space-y-2 px-2 py-2" aria-hidden aria-busy="true">
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="flex items-center gap-3 rounded-xl px-2 py-2.5">
          <Skeleton className="h-14 w-14 shrink-0" rounded="lg" />
          <div className="min-w-0 flex-1 space-y-2">
            <Skeleton className="h-3.5 w-3/4" rounded="md" />
            <Skeleton className="h-3 w-1/3" rounded="md" />
            <Skeleton className="h-2.5 w-1/4" rounded="md" />
          </div>
        </div>
      ))}
    </div>
  );
}
