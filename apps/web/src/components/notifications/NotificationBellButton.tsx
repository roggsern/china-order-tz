"use client";

import { useEffect, useRef, useState } from "react";
import { usePathname } from "next/navigation";
import { BellIcon } from "@/components/home/icons";
import { NotificationDropdown } from "@/components/notifications/NotificationDropdown";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
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
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [showInvite, setShowInvite] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const userId = isLoggedIn ? session?.email ?? null : null;

  const { notifications, unreadCount, isLoading, markRead, markAllRead } =
    useNotifications(userId);

  useEffect(() => {
    if (!open && !showInvite) {
      return;
    }

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
        setShowInvite(false);
      }
    };

    const onEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        setShowInvite(false);
      }
    };

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onEscape);

    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onEscape);
    };
  }, [open, showInvite]);

  if (!isReady) {
    return null;
  }

  if (!isLoggedIn || !userId) {
    return (
      <div ref={rootRef} className="relative">
        <button
          type="button"
          onClick={() => setShowInvite((value) => !value)}
          className={className}
          aria-label="Notifications — sign in required"
          aria-expanded={showInvite}
        >
          <span className="relative shrink-0">
            <BellIcon className={iconClassName} />
          </span>
          {showLabel ? <span className={labelClassName}>Alerts</span> : null}
        </button>

        {showInvite ? (
          <div className="absolute right-0 z-50 mt-3 w-[min(22rem,calc(100vw-2rem))] animate-fade-in">
            <AuthInvitationCard
              context="notifications"
              returnUrl={pathname || "/account"}
              compact
            />
          </div>
        ) : null}
      </div>
    );
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
