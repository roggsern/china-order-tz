"use client";

import { useCallback, useEffect, useState } from "react";
import {
  CustomerOrdersApiError,
  fetchCustomerOrder,
  fetchCustomerOrders,
  type CustomerOrderListItem,
} from "@/lib/api/customer-orders";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import {
  isAuthRequiredMessage,
  toFriendlyAuthMessage,
} from "@/lib/auth/friendly-auth-messages";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { OrdersPageSkeleton } from "@/components/ui/PageSkeletons";
import { OrdersSummaryIcon } from "@/components/account/AccountIcons";
import { OrderOverviewCard, type OrderOverviewCardData } from "./OrderOverviewCard";

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
    source: order.source,
    imageEmoji: "📦",
    imageGradient: "from-[#c9a227]/15 to-zinc-100",
  };
}

const DETAIL_ENRICH_LIMIT = 8;

async function enrichOrders(orders: CustomerOrderListItem[]): Promise<OrderOverviewCardData[]> {
  return Promise.all(
    orders.map(async (order, index) => {
      const base = toOverviewCard(order);
      if (index >= DETAIL_ENRICH_LIMIT) {
        return base;
      }

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
            extraCount > 0 ? `${first.name} +${extraCount} more` : first.name,
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
}

export function MyOrdersContent() {
  const [orders, setOrders] = useState<OrderOverviewCardData[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [needsAuth, setNeedsAuth] = useState(false);

  const loadOrders = useCallback(async () => {
    setIsLoading(true);
    setErrorMessage(null);
    setNeedsAuth(false);

    if (!getCustomerApiToken()) {
      setOrders([]);
      setNeedsAuth(true);
      setIsLoading(false);
      return;
    }

    try {
      const nextOrders = await fetchCustomerOrders();
      const cards = await enrichOrders(nextOrders);
      setOrders(cards);
    } catch (error) {
      setOrders([]);

      if (error instanceof CustomerOrdersApiError) {
        if (isAuthRequiredMessage(error.message) || error.statusCode === 401) {
          setNeedsAuth(true);
        } else {
          setErrorMessage(toFriendlyAuthMessage(error.message, error.message));
        }
      } else if (error instanceof Error) {
        if (isAuthRequiredMessage(error.message)) {
          setNeedsAuth(true);
        } else {
          setErrorMessage(error.message);
        }
      } else {
        setErrorMessage("Unable to load your orders.");
      }
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  if (isLoading) {
    return <OrdersPageSkeleton />;
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <header className="animate-fade-in">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">Account</p>
        <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
          My Orders
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          Track your orders, payment status, and shipping progress.
        </p>
      </header>

      {needsAuth ? (
        <div className="mt-10 animate-fade-in">
          <AuthInvitationCard context="orders" returnUrl="/orders" />
        </div>
      ) : errorMessage ? (
        <div className="mt-10 animate-fade-in">
          <ErrorState message={errorMessage} onRetry={() => void loadOrders()} />
        </div>
      ) : orders.length === 0 ? (
        <div className="mt-10 animate-fade-in">
          <EmptyState
            icon={<OrdersSummaryIcon className="h-8 w-8 text-[#8b6914]" />}
            title="No orders yet"
            description="Start shopping and your first order will appear here."
            primaryAction={{ label: "Start Shopping", href: "/products" }}
          />
        </div>
      ) : (
        <ul className="mt-8 space-y-4 animate-fade-in" aria-label="Order history">
          {orders.map((order) => (
            <li key={order.id}>
              <OrderOverviewCard order={order} />
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
