"use client";

import { useEffect, useRef, useState } from "react";
import { BellIcon } from "@/components/home/icons";
import { NotificationDropdown } from "@/components/notifications/NotificationDropdown";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { useNotifications } from "@/lib/notifications/use-notifications";

const defaultBadgeClass =
  "absolute -right-1.5 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#c9a227] px-1 text-[10px] font-bold text-zinc-900";

interface NotificationBellButtonProps {
  className?: string;
  iconClassName?: string;
  badgeClassName?: string;
  showLabel?: boolean;
  labelClassName?: string;
}

export function NotificationBellButton({
  className = "",
  iconClassName = "h-[18px] w-[18px]",
  badgeClassName = defaultBadgeClass,
  showLabel = false,
  labelClassName = "",
}: NotificationBellButtonProps) {
  const { session, isLoggedIn, isReady } = useCustomerSession();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const userId = isLoggedIn ? session?.email ?? null : null;

  const { notifications, unreadCount, isLoading, markRead, markAllRead } =
    useNotifications(userId);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    const onEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
      }
    };

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onEscape);

    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onEscape);
    };
  }, [open]);

  if (!isReady || !isLoggedIn || !userId) {
    return null;
  }

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className={className}
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ""}`}
        aria-expanded={open}
        aria-haspopup="dialog"
      >
        <span className="relative shrink-0">
          <BellIcon className={iconClassName} />
          {unreadCount > 0 ? (
            <span className={badgeClassName}>{unreadCount > 99 ? "99+" : unreadCount}</span>
          ) : null}
        </span>
        {showLabel ? <span className={labelClassName}>Alerts</span> : null}
      </button>

      <NotificationDropdown
        open={open}
        notifications={notifications}
        unreadCount={unreadCount}
        isLoading={isLoading}
        onMarkRead={(id) => {
          void markRead(id);
        }}
        onMarkAllRead={() => {
          void markAllRead();
        }}
        onClose={() => setOpen(false)}
      />
    </div>
  );
}
