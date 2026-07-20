"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  consumeQueuedCustomerToast,
  CUSTOMER_TOAST_EVENT,
  type CustomerToastPayload,
} from "@/lib/customer/customer-toast";
import { useCartDrawer } from "@/lib/cart/drawer-context";

const TOAST_DURATION_MS = 4_200;
const TOAST_EXIT_MS = 280;

export function CustomerToastHost() {
  const [toast, setToast] = useState<CustomerToastPayload | null>(null);
  const [visible, setVisible] = useState(false);
  const { open: openCartDrawer } = useCartDrawer();

  const showToast = useCallback((payload: CustomerToastPayload) => {
    setToast(payload);
    setVisible(false);
  }, []);

  useEffect(() => {
    const handleToastEvent = (event: Event) => {
      const detail = (event as CustomEvent<CustomerToastPayload>).detail;

      if (detail?.text || detail?.title) {
        showToast(detail);
        return;
      }

      const queued = consumeQueuedCustomerToast();
      if (queued) showToast(queued);
    };

    window.addEventListener(CUSTOMER_TOAST_EVENT, handleToastEvent);

    const queued = consumeQueuedCustomerToast();
    if (queued) showToast(queued);

    return () => {
      window.removeEventListener(CUSTOMER_TOAST_EVENT, handleToastEvent);
    };
  }, [showToast]);

  useEffect(() => {
    if (!toast) return;

    const showFrame = requestAnimationFrame(() => setVisible(true));
    const hideTimer = window.setTimeout(() => setVisible(false), TOAST_DURATION_MS);
    const removeTimer = window.setTimeout(() => setToast(null), TOAST_DURATION_MS + TOAST_EXIT_MS);

    return () => {
      cancelAnimationFrame(showFrame);
      window.clearTimeout(hideTimer);
      window.clearTimeout(removeTimer);
    };
  }, [toast]);

  if (!toast) return null;

  const title = toast.title ?? toast.text;
  const body = toast.title ? toast.text : toast.subtitle;
  const subtitle = toast.title ? toast.subtitle : undefined;

  return (
    <div
      role="status"
      aria-live="polite"
      className={`pointer-events-none fixed right-4 top-4 z-[100] w-[min(22rem,calc(100vw-2rem))] transition-all duration-300 ease-out ${
        visible ? "translate-x-0 opacity-100" : "translate-x-4 opacity-0"
      }`}
    >
      <div className="pointer-events-auto overflow-hidden rounded-2xl border border-[#c9a227]/25 bg-white shadow-[0_12px_40px_rgba(201,162,39,0.18)] ring-1 ring-zinc-900/5">
        <div className="flex gap-3 px-4 py-3.5">
          {toast.imageUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={toast.imageUrl}
              alt=""
              className="h-12 w-12 shrink-0 rounded-xl object-cover ring-1 ring-zinc-100"
            />
          ) : toast.icon ? (
            <span
              className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#c9a227]/20 bg-[#c9a227]/8 text-xl"
              aria-hidden
            >
              {toast.icon}
            </span>
          ) : null}

          <div className="min-w-0 flex-1">
            <p className="text-sm font-bold leading-snug text-zinc-900">{title}</p>
            {subtitle ? (
              <p className="mt-0.5 text-xs font-medium text-[#8b6914]">{subtitle}</p>
            ) : null}
            {body && body !== title ? (
              <p className="mt-1 whitespace-pre-line text-xs leading-relaxed text-zinc-500">
                {body}
              </p>
            ) : null}
          </div>

          <button
            type="button"
            onClick={() => {
              setVisible(false);
              window.setTimeout(() => setToast(null), TOAST_EXIT_MS);
            }}
            className="shrink-0 self-start rounded-lg p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
            aria-label="Dismiss notification"
          >
            ✕
          </button>
        </div>

        {toast.actions && toast.actions.length > 0 ? (
          <div className="flex gap-2 border-t border-zinc-100 bg-zinc-50/80 px-3 py-2.5">
            {toast.actions.map((action) => {
              if (action.onClickEvent === "open-cart") {
                return (
                  <button
                    key={action.label}
                    type="button"
                    onClick={() => {
                      openCartDrawer();
                      setVisible(false);
                      window.setTimeout(() => setToast(null), TOAST_EXIT_MS);
                    }}
                    className="flex-1 rounded-lg bg-zinc-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
                  >
                    {action.label}
                  </button>
                );
              }

              if (action.href) {
                return (
                  <Link
                    key={action.label}
                    href={action.href}
                    onClick={() => {
                      setVisible(false);
                      window.setTimeout(() => setToast(null), TOAST_EXIT_MS);
                    }}
                    className="flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-center text-xs font-semibold text-zinc-700 transition hover:border-[#c9a227]/40 hover:text-[#8b6914]"
                  >
                    {action.label}
                  </Link>
                );
              }

              return null;
            })}
          </div>
        ) : null}
      </div>
    </div>
  );
}
