import { Skeleton, SkeletonText } from "@/components/ui/Skeleton";

export function ProductDetailSkeleton() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" aria-busy="true" aria-label="Loading product">
      <Skeleton className="mb-6 h-4 w-48" rounded="md" />
      <div className="grid gap-8 lg:grid-cols-2 lg:gap-12">
        <div className="flex gap-3">
          <div className="hidden w-14 shrink-0 flex-col gap-2 sm:flex">
            {[1, 2, 3, 4].map((key) => (
              <Skeleton key={key} className="aspect-square w-full" rounded="2xl" />
            ))}
          </div>
          <Skeleton className="aspect-square w-full flex-1" rounded="3xl" />
        </div>
        <div className="space-y-5">
          <Skeleton className="h-3 w-24" rounded="md" />
          <Skeleton className="h-9 w-4/5" rounded="lg" />
          <Skeleton className="h-5 w-32" rounded="md" />
          <SkeletonText lines={3} />
          <div className="space-y-3 pt-2">
            <Skeleton className="h-4 w-40" rounded="md" />
            <div className="flex flex-wrap gap-2">
              {[1, 2, 3, 4].map((key) => (
                <Skeleton key={key} className="h-11 w-24" rounded="xl" />
              ))}
            </div>
          </div>
          <Skeleton className="h-28 w-full" rounded="2xl" />
          <div className="flex gap-3 pt-2">
            <Skeleton className="h-12 flex-1" rounded="xl" />
            <Skeleton className="h-12 flex-1" rounded="xl" />
          </div>
        </div>
      </div>
    </div>
  );
}

export function CartPageSkeleton() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8" aria-busy="true">
      <Skeleton className="h-8 w-40" rounded="lg" />
      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
        <div className="space-y-4">
          {[1, 2, 3].map((key) => (
            <div
              key={key}
              className="flex gap-4 rounded-2xl border border-zinc-100 bg-white p-4"
            >
              <Skeleton className="h-24 w-24 shrink-0" rounded="xl" />
              <div className="min-w-0 flex-1 space-y-3">
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-3 w-1/2" />
                <Skeleton className="h-8 w-28" rounded="lg" />
              </div>
            </div>
          ))}
        </div>
        <Skeleton className="h-80 w-full" rounded="3xl" />
      </div>
    </div>
  );
}

export function CheckoutPageSkeleton() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8" aria-busy="true">
      <Skeleton className="h-8 w-56" rounded="lg" />
      <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_400px]">
        <div className="space-y-6">
          <Skeleton className="h-72 w-full" rounded="3xl" />
          <Skeleton className="h-56 w-full" rounded="3xl" />
        </div>
        <Skeleton className="h-96 w-full" rounded="3xl" />
      </div>
    </div>
  );
}

export function OrdersPageSkeleton() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8" aria-busy="true">
      <Skeleton className="h-4 w-24" rounded="md" />
      <Skeleton className="mt-3 h-9 w-48" rounded="lg" />
      <Skeleton className="mt-2 h-4 w-72 max-w-full" rounded="md" />
      <div className="mt-8 space-y-4">
        {[1, 2, 3].map((key) => (
          <div
            key={key}
            className="flex gap-4 rounded-2xl border border-zinc-100 bg-white p-5"
          >
            <Skeleton className="h-20 w-20 shrink-0" rounded="xl" />
            <div className="min-w-0 flex-1 space-y-3">
              <Skeleton className="h-4 w-40" />
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-3 w-1/2" />
              <div className="flex gap-2 pt-1">
                <Skeleton className="h-9 w-24" rounded="xl" />
                <Skeleton className="h-9 w-24" rounded="xl" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export function AccountPageSkeleton() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8" aria-busy="true">
      <Skeleton className="h-44 w-full" rounded="3xl" />
      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((key) => (
          <Skeleton key={key} className="h-36 w-full" rounded="2xl" />
        ))}
      </div>
      <Skeleton className="mt-10 h-6 w-40" rounded="lg" />
      <div className="mt-5 space-y-4">
        {[1, 2].map((key) => (
          <Skeleton key={key} className="h-36 w-full" rounded="2xl" />
        ))}
      </div>
      <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {[1, 2, 3].map((key) => (
          <Skeleton key={key} className="h-40 w-full" rounded="2xl" />
        ))}
      </div>
    </div>
  );
}

export function WishlistPageSkeleton() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8" aria-busy="true">
      <Skeleton className="h-8 w-44" rounded="lg" />
      <div className="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
        {[1, 2, 3, 4, 5, 6].map((key) => (
          <div key={key} className="overflow-hidden rounded-2xl border border-zinc-100">
            <Skeleton className="aspect-square w-full" rounded="none" />
            <div className="space-y-2 p-3">
              <Skeleton className="h-3 w-3/4" />
              <Skeleton className="h-3 w-1/2" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
