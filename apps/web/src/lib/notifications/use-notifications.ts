"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { Notification } from "@/lib/notifications/types";
import { NOTIFICATIONS_POLL_MS, NOTIFICATIONS_UPDATED_EVENT } from "@/lib/notifications/constants";
import {
  fetchNotifications,
  markAllNotificationsReadApi,
  markNotificationReadApi,
} from "@/lib/notifications/notification-api";
import { subscribeNotificationsWs } from "@/lib/notifications/notification-ws";
import { normalizeUserId } from "@/lib/notifications/user-id";

export function useNotifications(userId: string | null | undefined) {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isLive, setIsLive] = useState(false);
  const userIdRef = useRef(userId);

  userIdRef.current = userId;

  const refresh = useCallback(async () => {
    const id = userIdRef.current;
    if (!id) {
      setNotifications([]);
      setUnreadCount(0);
      return;
    }

    try {
      const result = await fetchNotifications(id);
      setNotifications(result.notifications);
      setUnreadCount(result.unreadCount);
    } catch {
      // Keep last known state on transient failures.
    }
  }, []);

  useEffect(() => {
    if (!userId) {
      setNotifications([]);
      setUnreadCount(0);
      setIsLoading(false);
      return;
    }

    let cancelled = false;

    const load = async () => {
      setIsLoading(true);
      await refresh();
      if (!cancelled) {
        setIsLoading(false);
      }
    };

    void load();

    const onUpdated = () => {
      void refresh();
    };

    window.addEventListener(NOTIFICATIONS_UPDATED_EVENT, onUpdated);

    const intervalId = setInterval(() => {
      void refresh();
    }, NOTIFICATIONS_POLL_MS);

    const unsubscribeWs = subscribeNotificationsWs(normalizeUserId(userId), {
      onNotificationNew: () => {
        void refresh();
        window.dispatchEvent(new CustomEvent(NOTIFICATIONS_UPDATED_EVENT));
      },
      onConnected: () => setIsLive(true),
      onDisconnected: () => setIsLive(false),
    });

    return () => {
      cancelled = true;
      window.removeEventListener(NOTIFICATIONS_UPDATED_EVENT, onUpdated);
      clearInterval(intervalId);
      unsubscribeWs();
    };
  }, [userId, refresh]);

  const markRead = useCallback(async (notificationId: string) => {
    const id = userIdRef.current;
    if (!id) {
      return;
    }

    setNotifications((current) =>
      current.map((entry) =>
        entry.id === notificationId ? { ...entry, isRead: true } : entry,
      ),
    );
    setUnreadCount((count) => Math.max(0, count - 1));

    try {
      await markNotificationReadApi(id, notificationId);
      window.dispatchEvent(new CustomEvent(NOTIFICATIONS_UPDATED_EVENT));
    } catch {
      await refresh();
    }
  }, [refresh]);

  const markAllRead = useCallback(async () => {
    const id = userIdRef.current;
    if (!id) {
      return;
    }

    setNotifications((current) => current.map((entry) => ({ ...entry, isRead: true })));
    setUnreadCount(0);

    try {
      await markAllNotificationsReadApi(id);
      window.dispatchEvent(new CustomEvent(NOTIFICATIONS_UPDATED_EVENT));
    } catch {
      await refresh();
    }
  }, [refresh]);

  return {
    notifications,
    unreadCount,
    isLoading,
    isLive,
    refresh,
    markRead,
    markAllRead,
  };
}
