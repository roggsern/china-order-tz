"use client";

import Link from "next/link";
import { AnimatePresence, motion } from "framer-motion";
import type { Notification, NotificationType } from "@/lib/notifications/types";
import { NOTIFICATION_TYPE } from "@/lib/notifications/types";

function typeAccent(type: NotificationType): string {
  switch (type) {
    case NOTIFICATION_TYPE.PAYMENT:
      return "text-[#e8c547]";
    case NOTIFICATION_TYPE.SHIPPING:
      return "text-sky-400";
    case NOTIFICATION_TYPE.ORDER:
      return "text-emerald-400";
    default:
      return "text-zinc-300";
  }
}

function formatRelativeTime(iso: string): string {
  const diffMs = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diffMs / 60_000);
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

interface NotificationDropdownProps {
  open: boolean;
  notifications: Notification[];
  unreadCount: number;
  isLoading: boolean;
  onMarkRead: (id: string) => void;
  onMarkAllRead: () => void;
  onClose: () => void;
}

export function NotificationDropdown({
  open,
  notifications,
  unreadCount,
  isLoading,
  onMarkRead,
  onMarkAllRead,
  onClose,
}: NotificationDropdownProps) {
  return (
    <AnimatePresence>
      {open ? (
        <>
          <motion.button
            type="button"
            aria-label="Close notifications"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[70] bg-black/20 md:bg-transparent"
            onClick={onClose}
          />

          <motion.div
            role="dialog"
            aria-label="Notifications"
            initial={{ opacity: 0, y: -8, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -8, scale: 0.98 }}
            transition={{ duration: 0.18, ease: "easeOut" }}
            className="fixed right-3 top-[4.5rem] z-[80] w-[min(calc(100vw-1.5rem),24rem)] overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 shadow-[0_20px_60px_rgba(0,0,0,0.45)] md:absolute md:right-0 md:top-[calc(100%+0.75rem)] md:w-96"
          >
            <div className="flex items-center justify-between border-b border-zinc-800 px-4 py-3">
              <div>
                <p className="text-sm font-bold text-zinc-50">Notifications</p>
                <p className="text-[11px] text-zinc-500">
                  {unreadCount > 0 ? `${unreadCount} unread` : "You're all caught up"}
                </p>
              </div>
              {unreadCount > 0 ? (
                <button
                  type="button"
                  onClick={onMarkAllRead}
                  className="text-[11px] font-semibold text-[#c9a227] transition hover:text-[#e8c547]"
                >
                  Mark all read
                </button>
              ) : null}
            </div>

            <ul className="max-h-[min(24rem,60vh)] overflow-y-auto overscroll-contain">
              {isLoading && notifications.length === 0 ? (
                <li className="px-4 py-8 text-center text-sm text-zinc-500">Loading…</li>
              ) : null}

              {!isLoading && notifications.length === 0 ? (
                <li className="px-4 py-10 text-center">
                  <p className="text-sm font-medium text-zinc-300">No notifications yet</p>
                  <p className="mt-1 text-xs text-zinc-500">
                    Payment and delivery updates will appear here.
                  </p>
                </li>
              ) : null}

              {notifications.map((notification, index) => {
                const content = (
                  <>
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0 flex-1">
                        <p
                          className={`text-sm font-semibold ${notification.isRead ? "text-zinc-400" : "text-zinc-50"}`}
                        >
                          {notification.title}
                        </p>
                        <p className="mt-1 text-xs leading-relaxed text-zinc-500">
                          {notification.message}
                        </p>
                        <p className={`mt-2 text-[10px] font-bold uppercase tracking-wide ${typeAccent(notification.type)}`}>
                          {notification.type}
                          <span className="mx-1.5 text-zinc-700">·</span>
                          <span className="font-medium normal-case tracking-normal text-zinc-500">
                            {formatRelativeTime(notification.createdAt)}
                          </span>
                        </p>
                      </div>
                      {!notification.isRead ? (
                        <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#c9a227]" aria-hidden />
                      ) : null}
                    </div>
                  </>
                );

                return (
                  <motion.li
                    key={notification.id}
                    initial={{ opacity: 0, x: -8 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: index * 0.03, duration: 0.2 }}
                    className={`border-b border-zinc-900/80 last:border-b-0 ${notification.isRead ? "bg-zinc-950" : "bg-zinc-900/40"}`}
                  >
                    {notification.href ? (
                      <Link
                        href={notification.href}
                        onClick={() => {
                          if (!notification.isRead) {
                            onMarkRead(notification.id);
                          }
                          onClose();
                        }}
                        className="block px-4 py-3.5 transition hover:bg-zinc-900"
                      >
                        {content}
                      </Link>
                    ) : (
                      <button
                        type="button"
                        onClick={() => {
                          if (!notification.isRead) {
                            onMarkRead(notification.id);
                          }
                        }}
                        className="block w-full px-4 py-3.5 text-left transition hover:bg-zinc-900"
                      >
                        {content}
                      </button>
                    )}
                  </motion.li>
                );
              })}
            </ul>
          </motion.div>
        </>
      ) : null}
    </AnimatePresence>
  );
}
