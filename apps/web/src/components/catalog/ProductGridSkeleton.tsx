import { Skeleton } from "@/components/ui/Skeleton";

interface ProductGridSkeletonProps {
  count?: number;
}

export function ProductGridSkeleton({ count = 8 }: ProductGridSkeletonProps) {
  return (
    <div
      className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 lg:gap-6 xl:grid-cols-4"
      aria-hidden
      aria-busy="true"
    >
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white"
        >
          <Skeleton className="aspect-square w-full" rounded="none" />
          <div className="space-y-3 p-4">
            <Skeleton className="h-3 w-3/4" rounded="md" />
            <Skeleton className="h-3 w-1/2" rounded="md" />
            <Skeleton className="h-4 w-1/3" rounded="md" />
          </div>
        </div>
      ))}
    </div>
  );
}
