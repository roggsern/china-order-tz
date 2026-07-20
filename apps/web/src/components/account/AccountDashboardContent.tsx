"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { useCallback, useEffect, useState } from "react";
import {
  AddressSummaryIcon,
  HelpIcon,
  NotificationSummaryIcon,
  OrdersSummaryIcon,
  SettingsSummaryIcon,
  ShoppingBagIcon,
  TrackOrdersIcon,
  WishlistSummaryIcon,
} from "@/components/account/AccountIcons";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import { OrderOverviewCard, type OrderOverviewCardData } from "@/components/order/OrderOverviewCard";
import { EmptyState } from "@/components/ui/EmptyState";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { Skeleton } from "@/components/ui/Skeleton";
import {
  fetchCustomerOrder,
  fetchCustomerOrders,
  type CustomerOrderListItem,
} from "@/lib/api/customer-orders";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { useWishlist } from "@/lib/wishlist/use-wishlist";

function resolveDisplayName(name: string | undefined, email: string | undefined): string {
  if (name?.trim()) {
    return name.trim();
  }

  if (email?.trim()) {
    const localPart = email.split("@")[0]?.trim();
    if (localPart) {
      return localPart;
    }
  }

  return "there";
}

function toOverviewCard(order: CustomerOrderListItem): OrderOverviewCardData {
  return {
    id: order.id,
    orderNumber: order.orderNumber,
    status: order.status,
    paymentStatus: order.paymentStatus,
    createdAt: order.createdAt,
    grandTotal: order.grandTotal,
    productName: order.itemPreview,
    quantity: order.itemCount,
    imageEmoji: "📦",
    imageGradient: "from-[#c9a227]/15 to-zinc-100",
  };
}

async function enrichRecentOrders(
  orders: CustomerOrderListItem[],
): Promise<OrderOverviewCardData[]> {
  const enriched = await Promise.all(
    orders.map(async (order) => {
      const base = toOverviewCard(order);
      try {
        const detail = await fetchCustomerOrder(order.orderNumber);
        const first = detail.items[0];
        if (!first) {
          return base;
        }

        const totalQty = detail.items.reduce((sum, item) => sum + item.quantity, 0);
        const extraCount = detail.items.length - 1;

        return {
          ...base,
          productName:
            extraCount > 0
              ? `${first.name} +${extraCount} more`
              : first.name,
          quantity: totalQty,
          imageUrl: first.image?.url ?? null,
          imageEmoji: first.image?.emoji ?? "📦",
          imageGradient: first.image?.gradient ?? "from-[#c9a227]/15 to-zinc-100",
        };
      } catch {
        return base;
      }
    }),
  );

  return enriched;
}

type SummaryCard = {
  title: string;
  value: string;
  description: string;
  href: string;
  icon: ReactNode;
};

type QuickAction = {
  title: string;
  description: string;
  href: string;
  icon: ReactNode;
};

function SummaryMetricCard({ card }: { card: SummaryCard }) {
  return (
    <Link
      href={card.href}
      className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] transition duration-300 hover:-translate-y-0.5 hover:border-[#c9a227]/35 hover:shadow-[0_8px_28px_rgba(201,162,39,0.12)]"
    >
      <div
        className="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-[#c9a227]/8 transition group-hover:bg-[#c9a227]/15"
        aria-hidden
      />
      <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#c9a227]/12 text-[#8b6914] transition group-hover:bg-[#c9a227]/20">
        {card.icon}
      </span>
      <p className="mt-4 text-2xl font-bold tabular-nums tracking-tight text-zinc-900">
        {card.value}
      </p>
      <h3 className="mt-1 text-sm font-bold text-zinc-900 group-hover:text-[#8b6914]">
        {card.title}
      </h3>
      <p className="mt-1.5 text-xs leading-relaxed text-zinc-500">{card.description}</p>
    </Link>
  );
}

function QuickActionCard({ action }: { action: QuickAction }) {
  return (
    <Link
      href={action.href}
      className="group flex h-full flex-col rounded-2xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_16px_rgba(0,0,0,0.03)] transition duration-300 hover:-translate-y-1 hover:border-[#c9a227]/35 hover:shadow-[0_10px_30px_rgba(201,162,39,0.12)]"
    >
      <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-zinc-50 text-[#8b6914] ring-1 ring-zinc-100 transition group-hover:bg-[#c9a227]/12 group-hover:ring-[#c9a227]/20">
        {action.icon}
      </span>
      <h3 className="mt-4 text-base font-bold text-zinc-900 group-hover:text-[#8b6914]">
        {action.title}
      </h3>
      <p className="mt-1.5 flex-1 text-sm leading-relaxed text-zinc-500">{action.description}</p>
      <span className="mt-4 text-sm font-semibold text-[#8b6914] transition group-hover:text-[#c9a227]">
        Open →
      </span>
    </Link>
  );
}

function RecentOrdersSkeleton() {
  return (
    <div className="space-y-4" aria-busy="true">
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
  );
}

export function AccountDashboardContent() {
  const { session, isReady, isLoggedIn } = useCustomerSession();
  const { items: wishlistItems, ready: wishlistReady } = useWishlist();
  const displayName = resolveDisplayName(session?.name, session?.email);

  const [recentOrders, setRecentOrders] = useState<OrderOverviewCardData[]>([]);
  const [orderCount, setOrderCount] = useState(0);
  const [ordersLoading, setOrdersLoading] = useState(false);

  const loadRecentOrders = useCallback(async () => {
    if (!isLoggedIn || !getCustomerApiToken()) {
      setRecentOrders([]);
      setOrderCount(0);
      setOrdersLoading(false);
      return;
    }

    setOrdersLoading(true);
    try {
      const all = await fetchCustomerOrders();
      setOrderCount(all.length);
      const slice = all.slice(0, 3);
      const cards = await enrichRecentOrders(slice);
      setRecentOrders(cards);
    } catch {
      setRecentOrders([]);
      setOrderCount(0);
    } finally {
      setOrdersLoading(false);
    }
  }, [isLoggedIn]);

  useEffect(() => {
    if (!isReady) {
      return;
    }
    void loadRecentOrders();
  }, [isReady, loadRecentOrders]);

  if (!isReady) {
    return <AccountPageSkeleton />;
  }

  const wishlistCount = wishlistReady ? String(wishlistItems.length) : "—";

  const summaryCards: SummaryCard[] = [
    {
      title: "Orders",
      value: isLoggedIn ? String(orderCount) : "—",
      description: "Track purchases and delivery",
      href: "/orders",
      icon: <OrdersSummaryIcon className="h-5 w-5" />,
    },
    {
      title: "Wishlist",
      value: wishlistCount,
      description: "Saved products you love",
      href: "/wishlist",
      icon: <WishlistSummaryIcon className="h-5 w-5" />,
    },
    {
      title: "Addresses",
      value: "—",
      description: "Checkout faster next time",
      href: "/account/addresses",
      icon: <AddressSummaryIcon className="h-5 w-5" />,
    },
    {
      title: "Notifications",
      value: "—",
      description: "Order and delivery alerts",
      href: "/account/notifications",
      icon: <NotificationSummaryIcon className="h-5 w-5" />,
    },
    {
      title: "Loyalty",
      value: "—",
      description: "Points, tiers, and rewards",
      href: "/account/loyalty",
      icon: <OrdersSummaryIcon className="h-5 w-5" />,
    },
  ];

  const quickActions: QuickAction[] = [
    {
      title: "Continue Shopping",
      description: "Browse new arrivals and bestsellers.",
      href: "/products",
      icon: <ShoppingBagIcon className="h-5 w-5" />,
    },
    {
      title: "Track Orders",
      description: "Follow your packages in real time.",
      href: "/track",
      icon: <TrackOrdersIcon className="h-5 w-5" />,
    },
    {
      title: "Saved Addresses",
      description: "Manage delivery locations soon.",
      href: "/account/addresses",
      icon: <AddressSummaryIcon className="h-5 w-5" />,
    },
    {
      title: "Wishlist",
      description: "Revisit products you saved.",
      href: "/wishlist",
      icon: <WishlistSummaryIcon className="h-5 w-5" />,
    },
    {
      title: "Need Help",
      description: "Get support with orders or delivery.",
      href: "/#contact",
      icon: <HelpIcon className="h-5 w-5" />,
    },
  ];

  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      {!isLoggedIn ? (
        <AuthInvitationCard context="account" returnUrl="/account" className="mb-8 animate-fade-in" />
      ) : null}

      <header className="relative overflow-hidden rounded-3xl border border-zinc-200/70 bg-white p-6 shadow-[0_2px_24px_rgba(0,0,0,0.05)] animate-fade-in sm:p-8 lg:p-10">
        <div
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(201,162,39,0.14),_transparent_55%),radial-gradient(ellipse_at_bottom_left,_rgba(232,197,71,0.1),_transparent_50%)]"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-[#c9a227]/10 blur-3xl"
          aria-hidden
        />

        <div className="relative">
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            My Account
          </p>
          <h1 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl lg:text-4xl">
            {isLoggedIn ? (
              <>
                Welcome back, {displayName}{" "}
                <span aria-hidden>👋</span>
              </>
            ) : (
              "You're one step away!"
            )}
          </h1>
          <p className="mt-3 max-w-2xl text-sm leading-relaxed text-zinc-500 sm:text-base">
            {isLoggedIn
              ? "Manage your orders, deliveries, saved items and account preferences."
              : "Sign in to continue where you left off — your cart stays saved."}
          </p>
          {session?.email ? (
            <p className="mt-4 text-sm text-zinc-400">{session.email}</p>
          ) : null}
        </div>
      </header>

      <section aria-labelledby="account-summary-heading" className="mt-8 animate-fade-in">
        <h2 id="account-summary-heading" className="sr-only">
          Account summary
        </h2>
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {summaryCards.map((card) => (
            <li key={card.title}>
              <SummaryMetricCard card={card} />
            </li>
          ))}
        </ul>
      </section>

      {isLoggedIn ? (
        <section aria-labelledby="recent-orders-heading" className="mt-10 animate-fade-in">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 id="recent-orders-heading" className="text-lg font-bold text-zinc-900 sm:text-xl">
                Recent Orders
              </h2>
              <p className="mt-1 text-sm text-zinc-500">
                Your latest purchases at a glance.
              </p>
            </div>
            <Link
              href="/orders"
              className="text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
            >
              View all orders →
            </Link>
          </div>

          <div className="mt-5">
            {ordersLoading ? (
              <RecentOrdersSkeleton />
            ) : recentOrders.length === 0 ? (
              <EmptyState
                icon={<OrdersSummaryIcon className="h-8 w-8 text-[#8b6914]" />}
                title="No orders yet"
                description="Start shopping and your first order will appear here."
                primaryAction={{ label: "Start Shopping", href: "/products" }}
                tone="compact"
                className="rounded-3xl border border-dashed border-zinc-200 bg-zinc-50/80"
              />
            ) : (
              <ul className="space-y-4" aria-label="Recent orders">
                {recentOrders.map((order) => (
                  <li key={order.id}>
                    <OrderOverviewCard order={order} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>
      ) : null}

      <section aria-labelledby="account-actions-heading" className="mt-10 animate-fade-in">
        <h2 id="account-actions-heading" className="text-lg font-bold text-zinc-900 sm:text-xl">
          Quick Actions
        </h2>
        <p className="mt-1 text-sm text-zinc-500">
          Jump into shopping, tracking, and account tools.
        </p>
        <ul className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {quickActions.map((action) => (
            <li key={action.title}>
              <QuickActionCard action={action} />
            </li>
          ))}
        </ul>
      </section>

      <section
        aria-labelledby="account-settings-heading"
        className="mt-10 animate-fade-in rounded-3xl border border-zinc-200/70 bg-white p-6 shadow-[0_2px_24px_rgba(0,0,0,0.04)] sm:p-8"
      >
        <div className="flex items-start gap-4">
          <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#c9a227]/12 text-[#8b6914]">
            <SettingsSummaryIcon className="h-6 w-6" />
          </span>
          <div className="min-w-0 flex-1">
            <h2 id="account-settings-heading" className="text-lg font-bold text-zinc-900">
              Account Settings
            </h2>
            <p className="mt-2 max-w-xl text-sm leading-relaxed text-zinc-500">
              Update your profile details, communication preferences, and security options from one
              calm place.
            </p>
            <dl className="mt-6 grid gap-4 sm:grid-cols-2">
              <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4">
                <dt className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
                  Profile
                </dt>
                <dd className="mt-2 text-sm font-semibold text-zinc-900">{displayName}</dd>
                {session?.email ? (
                  <dd className="mt-1 truncate text-sm text-zinc-500">{session.email}</dd>
                ) : (
                  <dd className="mt-1 text-sm text-zinc-400">Sign in to view your email</dd>
                )}
              </div>
              <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4">
                <dt className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
                  Preferences
                </dt>
                <dd className="mt-2 text-sm font-semibold text-zinc-900">Notifications & privacy</dd>
                <dd className="mt-1 text-sm text-zinc-500">
                  Advanced preference controls are on the way.
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </section>
    </div>
  );
}
