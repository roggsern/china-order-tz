import Link from "next/link";

export function CartEmptyState() {
  return (
    <div className="rounded-3xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-16 text-center">
      <p className="text-5xl" aria-hidden>
        🛒
      </p>
      <h2 className="mt-4 text-xl font-semibold text-zinc-900">Your cart is empty</h2>
      <p className="mt-2 text-sm text-zinc-500">
        Browse our catalog and add products to get started.
      </p>
      <Link
        href="/products"
        className="mt-6 inline-flex items-center justify-center rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
      >
        Start Shopping
      </Link>
    </div>
  );
}
