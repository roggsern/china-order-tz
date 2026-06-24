import Link from "next/link";

export default function ProductNotFound() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
      <span className="text-6xl">📦</span>
      <h1 className="mt-6 text-2xl font-bold text-zinc-900">Product Not Found</h1>
      <p className="mt-2 max-w-md text-zinc-500">
        The product you&apos;re looking for doesn&apos;t exist or may have been removed.
      </p>
      <div className="mt-8 flex gap-3">
        <Link
          href="/products"
          className="rounded-full bg-zinc-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
        >
          Browse Products
        </Link>
        <Link
          href="/categories"
          className="rounded-full border border-zinc-200 px-6 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
        >
          View Categories
        </Link>
      </div>
    </div>
  );
}
