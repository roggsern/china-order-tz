"use client";

import Link from "next/link";
import { useMemo } from "react";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { computeOrderAnalytics } from "@/lib/admin/order-analytics";
import { formatPrice } from "@/lib/catalog/utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { PlusIcon, PackageIcon, ChartBarIcon } from "@/components/home/icons";
import { StatusBadge } from "@/components/admin/StatusBadge";

export function AdminDashboard() {
  const { products } = useAdminProducts();
  const { orders, isHydrated: ordersHydrated } = useAdminOrders();

  const orderAnalytics = useMemo(() => computeOrderAnalytics(orders), [orders]);

  const activeCount = products.filter((p) => p.status === "active").length;
  const hiddenCount = products.filter((p) => p.status === "hidden").length;
  const featuredCount = products.filter((p) => p.featured).length;
  const inStockCount = products.filter((p) => p.stock > 0).length;
  const outOfStockCount = products.filter((p) => p.stock <= 0).length;
  const onSaleCount = products.filter((p) => p.oldPrice > p.price).length;
  const totalValue = products.reduce((sum, p) => sum + p.price * p.stock, 0);

  const recentProducts = [...products].sort((a, b) => b.id - a.id).slice(0, 5);

  const categoryBreakdown = products.reduce<Record<string, number>>((acc, p) => {
    acc[p.categorySlug] = (acc[p.categorySlug] ?? 0) + 1;
    return acc;
  }, {});

  const topCategories = Object.entries(categoryBreakdown)
    .sort(([, a], [, b]) => b - a)
    .slice(0, 4);

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">Dashboard</h1>
          <p className="mt-0.5 text-sm text-zinc-500">
            Store overview — orders, revenue, and catalog at a glance.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            href="/admin/orders"
            className="inline-flex items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
          >
            View orders
          </Link>
          <Link
            href="/admin/products/new"
            className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#c9a227] px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
          >
            <PlusIcon className="h-4 w-4" />
            Add product
          </Link>
        </div>
      </div>

      <section className="mt-6">
        <h2 className="text-sm font-semibold text-zinc-900">Order analytics</h2>
        <div className="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Total orders"
            value={ordersHydrated ? orderAnalytics.totalOrders : "—"}
            href="/admin/orders"
          />
          <StatCard
            label="Total revenue"
            value={ordersHydrated ? formatPrice(orderAnalytics.totalRevenue) : "—"}
            sub="Paid orders only"
            isText
          />
          <StatCard
            label="Paid orders"
            value={ordersHydrated ? orderAnalytics.paidOrders : "—"}
            accent="text-emerald-600"
          />
          <StatCard
            label="Pending payments"
            value={ordersHydrated ? orderAnalytics.pendingPayments : "—"}
            accent="text-amber-600"
          />
        </div>
      </section>

      {/* Catalog stats */}
      <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total products"
          value={products.length}
          icon={<PackageIcon className="h-5 w-5 text-[#8b6914]" />}
          href="/admin/products"
        />
        <StatCard
          label="Active products"
          value={activeCount}
          sub={`${hiddenCount} hidden`}
          icon={<ChartBarIcon className="h-5 w-5 text-emerald-600" />}
        />
        <StatCard
          label="In stock"
          value={inStockCount}
          sub={`${outOfStockCount} out of stock`}
          icon={<PackageIcon className="h-5 w-5 text-blue-600" />}
        />
        <StatCard
          label="Inventory value"
          value={formatPrice(totalValue)}
          sub={`${onSaleCount} on sale · ${featuredCount} featured`}
          isText
        />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        {/* Recent products */}
        <section className="admin-card lg:col-span-2">
          <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
            <h2 className="text-sm font-semibold text-zinc-900">Recent products</h2>
            <Link
              href="/admin/products"
              className="text-xs font-medium text-[#8b6914] hover:underline"
            >
              View all
            </Link>
          </div>
          <ul className="divide-y divide-zinc-100">
            {recentProducts.map((product) => {
              const category = getCategoryBySlug(product.categorySlug);
              return (
                <li key={product.id}>
                  <Link
                    href={`/admin/products/${product.id}/edit`}
                    className="flex items-center gap-3 px-5 py-3 transition hover:bg-zinc-50"
                  >
                    <div
                      className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br ${product.gradient}`}
                    >
                      <span className="text-lg">{product.emoji}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-zinc-900">{product.name}</p>
                      <p className="text-xs text-zinc-500">{category?.name}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium text-zinc-900">
                        {formatPrice(product.price)}
                      </p>
                      <StatusBadge status={product.status} />
                    </div>
                  </Link>
                </li>
              );
            })}
          </ul>
        </section>

        {/* Category breakdown */}
        <section className="admin-card">
          <div className="border-b border-zinc-200 px-5 py-4">
            <h2 className="text-sm font-semibold text-zinc-900">Top categories</h2>
          </div>
          <ul className="divide-y divide-zinc-100 p-2">
            {topCategories.map(([slug, count]) => {
              const category = getCategoryBySlug(slug);
              const pct = Math.round((count / products.length) * 100);
              return (
                <li key={slug} className="px-3 py-3">
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-medium text-zinc-800">
                      {category?.icon} {category?.name ?? slug}
                    </span>
                    <span className="text-zinc-500">{count}</span>
                  </div>
                  <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100">
                    <div
                      className="h-full rounded-full bg-[#c9a227]"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </li>
              );
            })}
          </ul>
        </section>
      </div>

      {/* Quick actions */}
      <section className="admin-card mt-6 p-5">
        <h2 className="text-sm font-semibold text-zinc-900">Quick actions</h2>
        <div className="mt-4 flex flex-wrap gap-3">
          <Link
            href="/admin/products/new"
            className="rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Add new product
          </Link>
          <Link
            href="/admin/orders"
            className="rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Manage orders
          </Link>
          <Link
            href="/admin/products"
            className="rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            Manage products
          </Link>
          <Link
            href="/"
            className="rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50"
          >
            View storefront
          </Link>
        </div>
      </section>
    </div>
  );
}

function StatCard({
  label,
  value,
  sub,
  icon,
  href,
  isText,
  accent,
}: {
  label: string;
  value: number | string;
  sub?: string;
  icon?: React.ReactNode;
  href?: string;
  isText?: boolean;
  accent?: string;
}) {
  const content = (
    <div className="admin-card p-5 transition hover:shadow-md">
      <div className="flex items-start justify-between">
        <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">{label}</p>
        {icon}
      </div>
      <p className={`mt-2 font-semibold ${accent ?? "text-zinc-900"} ${isText ? "text-lg" : "text-2xl"}`}>
        {value}
      </p>
      {sub && <p className="mt-1 text-xs text-zinc-500">{sub}</p>}
    </div>
  );

  if (href) {
    return (
      <Link href={href} className="block">
        {content}
      </Link>
    );
  }

  return content;
}
