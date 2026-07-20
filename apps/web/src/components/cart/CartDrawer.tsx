"use client";

import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { useCartState } from "@/lib/cart/context";
import { useCartDrawer } from "@/lib/cart/drawer-context";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { CloseIcon } from "@/components/home/icons";
import { OrderSummaryTotals } from "./OrderSummaryTotals";
import { CartDrawerItem } from "./CartDrawerItem";

function useIsDesktop() {
  const [isDesktop, setIsDesktop] = useState(false);

  useEffect(() => {
    const media = window.matchMedia("(min-width: 768px)");
    const update = () => setIsDesktop(media.matches);
    update();
    media.addEventListener("change", update);
    return () => media.removeEventListener("change", update);
  }, []);

  return isDesktop;
}

export function CartDrawer() {
  const router = useRouter();
  const reduceMotion = useReducedMotion();
  const isDesktop = useIsDesktop();
  const { isOpen, drawerActive, close } = useCartDrawer();
  const { items, totals, isHydrated } = useCartState();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted || !isOpen) {
    return null;
  }

  const hiddenAxis = isDesktop ? { x: "100%", y: 0 } : { x: 0, y: "100%" };

  const handleCheckout = () => {
    if (items.length === 0) return;
    clearCheckoutDraft();
    close();
    window.setTimeout(() => router.push("/checkout"), 280);
  };

  return createPortal(
    <div className="fixed inset-0 z-[65]" role="presentation">
      <button
        type="button"
        aria-label="Close cart"
        onClick={close}
        className={`absolute inset-0 bg-black/50 backdrop-blur-[2px] transition-opacity duration-300 ease-out ${
          drawerActive ? "opacity-100" : "opacity-0"
        }`}
      />

      <motion.aside
        role="dialog"
        aria-modal="true"
        aria-label="Shopping cart"
        initial={false}
        animate={
          drawerActive
            ? { x: 0, y: 0, opacity: 1 }
            : reduceMotion
              ? { x: 0, y: 0, opacity: 0 }
              : { ...hiddenAxis, opacity: 1 }
        }
        transition={{ type: "spring", stiffness: 360, damping: 34 }}
        className="fixed bottom-0 left-0 z-10 flex max-h-[92vh] w-full flex-col rounded-t-3xl bg-white shadow-[0_-12px_48px_rgba(0,0,0,0.15)] md:inset-y-0 md:right-0 md:bottom-auto md:left-auto md:h-full md:max-h-none md:max-w-[420px] md:rounded-none md:shadow-[-8px_0_32px_rgba(0,0,0,0.12)]"
      >
        <DrawerHeader itemCount={isHydrated ? totals.itemCount : 0} onClose={close} />

        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-2 md:px-6">
          <DrawerBody isHydrated={isHydrated} items={items} onClose={close} />
        </div>

        {isHydrated && items.length > 0 && (
          <DrawerFooter totals={totals} onCheckout={handleCheckout} onClose={close} />
        )}
      </motion.aside>
    </div>,
    document.body,
  );
}

function DrawerHeader({
  itemCount,
  onClose,
}: {
  itemCount: number;
  onClose: () => void;
}) {
  return (
    <div className="flex shrink-0 items-center justify-between border-b border-zinc-100 px-5 py-4 md:px-6">
      <div>
        <h2 className="text-lg font-bold text-zinc-900">Your Cart</h2>
        <p className="mt-0.5 text-xs text-zinc-500">
          {itemCount} {itemCount === 1 ? "item" : "items"}
        </p>
      </div>
      <button
        type="button"
        onClick={onClose}
        className="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
        aria-label="Close cart"
      >
        <CloseIcon className="h-5 w-5" />
      </button>
    </div>
  );
}

function DrawerBody({
  isHydrated,
  items,
  onClose,
}: {
  isHydrated: boolean;
  items: ReturnType<typeof useCartState>["items"];
  onClose: () => void;
}) {
  if (!isHydrated) {
    return (
      <div className="space-y-3 py-4" aria-busy="true" aria-label="Loading cart">
        {[1, 2].map((key) => (
          <div key={key} className="flex gap-3 rounded-xl border border-zinc-100 p-3">
            <div className="h-16 w-16 shrink-0 skeleton-shimmer rounded-lg" />
            <div className="flex-1 space-y-2">
              <div className="h-3 w-3/4 skeleton-shimmer rounded" />
              <div className="h-3 w-1/2 skeleton-shimmer rounded" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="py-10 text-center">
        <span
          className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-[#c9a227]/25 bg-[#c9a227]/8 text-2xl"
          aria-hidden
        >
          🛒
        </span>
        <p className="mt-4 text-base font-bold text-zinc-900">Your cart is waiting</p>
        <p className="mt-1 text-sm text-zinc-500">Discover products and start shopping.</p>
        <Link
          href="/products"
          onClick={onClose}
          className="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-2.5 text-sm font-bold text-zinc-900 shadow-md shadow-[#c9a227]/25 transition hover:brightness-105"
        >
          Continue Shopping
        </Link>
      </div>
    );
  }

  return (
    <AnimatePresence initial={false}>
      {items.map((item) => (
        <motion.div
          key={item.id}
          layout
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: "auto" }}
          exit={{ opacity: 0, height: 0 }}
          transition={{ duration: 0.2 }}
        >
          <CartDrawerItem item={item} />
        </motion.div>
      ))}
    </AnimatePresence>
  );
}

function DrawerFooter({
  totals,
  onCheckout,
  onClose,
}: {
  totals: ReturnType<typeof useCartState>["totals"];
  onCheckout: () => void;
  onClose: () => void;
}) {
  return (
    <div className="shrink-0 border-t border-zinc-100 bg-white px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:px-6">
      <OrderSummaryTotals totals={totals} variant="cart" hideZeroDiscount />

      <button
        type="button"
        onClick={onCheckout}
        className="mt-4 flex w-full items-center justify-center rounded-xl bg-zinc-900 py-3.5 text-sm font-bold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
      >
        Proceed to Checkout
      </button>

      <Link
        href="/cart"
        onClick={onClose}
        className="mt-2.5 block text-center text-sm font-medium text-zinc-500 transition hover:text-[#8b6914]"
      >
        View full cart
      </Link>
    </div>
  );
}
