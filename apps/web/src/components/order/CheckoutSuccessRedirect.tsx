"use client";

import { useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { paymentService } from "@/lib/payment/PaymentService";

interface CheckoutSuccessRedirectProps {
  orderNumber: string;
}

/** Legacy /checkout/success/:orderNumber → /order-success/:orderId */
export function CheckoutSuccessRedirect({ orderNumber }: CheckoutSuccessRedirectProps) {
  const router = useRouter();

  useEffect(() => {
    const order = paymentService.getOrder(orderNumber);
    if (order) {
      router.replace(`/order-success/${order.id}`);
      return;
    }

    router.replace("/orders");
  }, [orderNumber, router]);

  return (
    <div className="mx-auto max-w-3xl px-4 py-16 sm:px-6" aria-busy="true">
      <div className="mx-auto h-16 w-16 animate-pulse rounded-full bg-zinc-100" />
      <div className="mx-auto mt-6 h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
      <p className="mt-6 text-center text-sm text-zinc-500">Redirecting to your confirmation…</p>
      <Link
        href="/orders"
        className="mt-4 block text-center text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
      >
        View orders
      </Link>
    </div>
  );
}
