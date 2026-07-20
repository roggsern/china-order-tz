"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { fetchCustomerOrder } from "@/lib/api/customer-orders";
import {
  PaymentOrchestratorApiError,
  startPaymentTransaction,
} from "@/lib/api/customer-payment-orchestrator";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";

/**
 * Starts a Payment Orchestrator transaction for an order, then redirects
 * to the payment status page.
 */
export default function OrderPayPage() {
  const params = useParams<{ orderNumber: string }>();
  const router = useRouter();
  const orderNumber = decodeURIComponent(params.orderNumber ?? "");
  const [error, setError] = useState<string | null>(null);
  const [needsAuth, setNeedsAuth] = useState(false);

  useEffect(() => {
    const token = getCustomerApiToken();
    if (!token) {
      setNeedsAuth(true);
      return;
    }

    let cancelled = false;

    void (async () => {
      try {
        const order = await fetchCustomerOrder(orderNumber, token);
        const transaction = await startPaymentTransaction(order.id, "nmb", token);
        if (!cancelled) {
          router.replace(`/payments/${encodeURIComponent(transaction.id)}`);
        }
      } catch (err) {
        if (cancelled) return;
        if (err instanceof PaymentOrchestratorApiError && err.statusCode === 401) {
          setNeedsAuth(true);
          return;
        }
        setError(err instanceof Error ? err.message : "Unable to start payment.");
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [orderNumber, router]);

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <AuthInvitationCard
          context="checkout"
          returnUrl={`/orders/${encodeURIComponent(orderNumber)}/pay`}
        />
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <div className="rounded-2xl border border-red-200 bg-red-50 p-5" role="alert">
          <p className="text-sm font-semibold text-red-900">Could not start payment</p>
          <p className="mt-1 text-sm text-red-800">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-lg px-4 py-16 text-center text-sm text-zinc-500">
      Starting payment with NMB…
    </div>
  );
}
