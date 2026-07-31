"use client";

import Link from "next/link";

interface AdminOrderFulfilmentWorkspaceCardProps {
  orderId: string;
}

export function AdminOrderFulfilmentWorkspaceCard({
  orderId,
}: AdminOrderFulfilmentWorkspaceCardProps) {
  return (
    <section className="admin-card p-4 sm:p-5">
      <h2 className="text-sm font-bold text-zinc-900">Fulfilment Management</h2>
      <p className="mt-2 text-sm leading-relaxed text-zinc-600">
        This order is managed from the Fulfilment workspace.
      </p>
      <Link
        href="/admin/fulfillments"
        className="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800"
      >
        Open Fulfilment
      </Link>
      <p className="mt-3 font-mono text-[11px] text-zinc-400">Order ID: {orderId}</p>
    </section>
  );
}
