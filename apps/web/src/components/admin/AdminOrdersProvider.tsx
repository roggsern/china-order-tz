"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type { BulkOrderStatus } from "@/lib/admin/bulk-order-status";
import { mapBulkOrderStatus } from "@/lib/admin/bulk-order-status";
import { bulkUpdateOrderStatus as bulkUpdateOrderStatusApi } from "@/lib/admin/bulk-order-update-client";
import {
  fetchInitialAdminOrders,
  patchOrderInList,
  upsertOrderInList,
} from "@/lib/admin/admin-orders-ws";
import {
  getAdminRealtimeTransport,
  subscribeAdminOrdersRealtime,
} from "@/lib/admin/admin-orders-realtime";
import type { AdminRealtimeTransport } from "@/lib/admin/realtime-config";
import { ADMIN_NEW_ORDER_HIGHLIGHT_MS } from "@/lib/admin/constants";
import {
  advanceDelivery,
  assignDeliveryDriver as assignDeliveryDriverApi,
} from "@/lib/delivery/delivery-api";
import { DELIVERIES_UPDATED_EVENT } from "@/lib/delivery/delivery-labels";
import { DELIVERY_STATUS } from "@/lib/delivery/types";
import { paymentService } from "@/lib/payment/PaymentService";
import { updateOrderById } from "@/lib/payment/order-storage";
import { isAdminLocalOrderAuthorityEnabled } from "@/lib/config/env";

type AdminOrdersContextValue = {
  orders: Order[];
  isHydrated: boolean;
  isLive: boolean;
  wsConnected: boolean;
  realtimeTransport: AdminRealtimeTransport;
  lastSyncedAt: Date | null;
  newOrderIds: ReadonlySet<string>;
  refreshOrders: () => void;
  markPaymentReceived: (orderId: string) => void;
  markOrderShipped: (orderId: string) => Promise<void>;
  markOrderDelivered: (orderId: string) => Promise<void>;
  markOrderProcessing: (orderId: string) => void;
  assignDeliveryDriver: (orderId: string, driverName: string) => Promise<void>;
  updateOrderStatus: (orderId: string, status: OrderStatus) => void;
  bulkUpdateOrderStatus: (orderIds: string[], status: BulkOrderStatus) => Promise<void>;
  isBulkUpdating: boolean;
  /** When false, UI must not mutate top-level order status locally. */
  localOrderAuthorityEnabled: boolean;
  getOrder: (orderNumber: string) => Order | undefined;
  getOrderById: (orderId: string) => Order | undefined;
};

const AdminOrdersContext = createContext<AdminOrdersContextValue | null>(null);

function findOrderById(orders: Order[], orderId: string): Order | undefined {
  return orders.find((order) => order.id === orderId);
}

export function AdminOrdersProvider({ children }: { children: ReactNode }) {
  const [orders, setOrders] = useState<Order[]>([]);
  const [isHydrated, setIsHydrated] = useState(false);
  const [wsConnected, setWsConnected] = useState(false);
  const [realtimeTransport] = useState<AdminRealtimeTransport>(() => getAdminRealtimeTransport());
  const [lastSyncedAt, setLastSyncedAt] = useState<Date | null>(null);
  const [newOrderIds, setNewOrderIds] = useState<Set<string>>(() => new Set());
  const [isBulkUpdating, setIsBulkUpdating] = useState(false);

  const ordersRef = useRef<Order[]>([]);
  const prevOrderIdsRef = useRef<Set<string>>(new Set());
  const highlightTimersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());
  const hydratedRef = useRef(false);
  const wsConnectedRef = useRef(false);

  ordersRef.current = orders;

  const setWsConnectedIfChanged = useCallback((connected: boolean) => {
    if (wsConnectedRef.current === connected) {
      return;
    }
    wsConnectedRef.current = connected;
    setWsConnected(connected);
  }, []);

  const highlightNewOrders = useCallback((addedIds: string[]) => {
    if (addedIds.length === 0) {
      return;
    }

    setNewOrderIds((prev) => {
      const next = new Set(prev);
      for (const id of addedIds) {
        next.add(id);
      }
      return next;
    });

    for (const id of addedIds) {
      const existing = highlightTimersRef.current.get(id);
      if (existing) {
        clearTimeout(existing);
      }

      const timer = setTimeout(() => {
        setNewOrderIds((prev) => {
          const next = new Set(prev);
          next.delete(id);
          return next;
        });
        highlightTimersRef.current.delete(id);
      }, ADMIN_NEW_ORDER_HIGHLIGHT_MS);

      highlightTimersRef.current.set(id, timer);
    }
  }, []);

  const trackNewOrderIds = useCallback(
    (order: Order, isCreate: boolean) => {
      if (!isCreate || prevOrderIdsRef.current.has(order.id)) {
        return;
      }

      if (prevOrderIdsRef.current.size > 0) {
        highlightNewOrders([order.id]);
      }
    },
    [highlightNewOrders],
  );

  const handleOrderUpsert = useCallback(
    (order: Order, isCreate: boolean) => {
      trackNewOrderIds(order, isCreate);
      prevOrderIdsRef.current.add(order.id);

      setOrders((current) => {
        const { next, changed } = upsertOrderInList(current, order);
        if (!changed) {
          return current;
        }
        ordersRef.current = next;
        setLastSyncedAt(new Date());
        return next;
      });
    },
    [trackNewOrderIds],
  );

  const handleOrderPatch = useCallback(
    (orderId: string, patch: Parameters<typeof patchOrderInList>[2]) => {
      setOrders((current) => {
        const { next, changed } = patchOrderInList(current, orderId, patch);
        if (!changed) {
          return current;
        }
        ordersRef.current = next;
        setLastSyncedAt(new Date());
        return next;
      });
    },
    [],
  );

  const handleOrderUpsertRef = useRef(handleOrderUpsert);
  handleOrderUpsertRef.current = handleOrderUpsert;

  const handleOrderPatchRef = useRef(handleOrderPatch);
  handleOrderPatchRef.current = handleOrderPatch;

  const bootstrapOrdersRef = useRef<() => Promise<void>>(async () => {});
  bootstrapOrdersRef.current = async () => {
    const initial = await fetchInitialAdminOrders();
    prevOrderIdsRef.current = new Set(initial.map((order) => order.id));
    ordersRef.current = initial;
    setOrders(initial);
    setLastSyncedAt(new Date());

    if (!hydratedRef.current) {
      hydratedRef.current = true;
      setIsHydrated(true);
    }
  };

  useEffect(() => {
    let cancelled = false;

    void bootstrapOrdersRef.current().then(() => {
      if (cancelled) {
        return;
      }
    });

    const subscription = subscribeAdminOrdersRealtime({
      onConnected: () => setWsConnectedIfChanged(true),
      onDisconnected: () => setWsConnectedIfChanged(false),
      onOrderCreated: (order) => handleOrderUpsertRef.current(order, true),
      onOrderUpdated: (order) => handleOrderUpsertRef.current(order, false),
      onOrderPatch: (orderId, message) => handleOrderPatchRef.current(orderId, message.patch),
      onAnalyticsUpdate: () => {
        void bootstrapOrdersRef.current();
      },
      onDeliveryUpdate: (event) => {
        const existing = paymentService.getOrderById(event.orderId);
        if (existing) {
          updateOrderById(event.orderId, (order) => ({
            ...order,
            status: event.order.status,
            paymentStatus: event.order.paymentStatus,
            updatedAt: event.order.updatedAt,
          }));
          const updated = paymentService.getOrderById(event.orderId);
          if (updated) {
            handleOrderUpsertRef.current(updated, false);
          }
        }
        if (typeof window !== "undefined") {
          window.dispatchEvent(new CustomEvent(DELIVERIES_UPDATED_EVENT));
        }
      },
    });

    const highlightTimers = highlightTimersRef.current;

    return () => {
      cancelled = true;
      subscription.unsubscribe();

      for (const timer of highlightTimers.values()) {
        clearTimeout(timer);
      }
      highlightTimers.clear();
    };
    // Mount-only: WebSocket + initial fetch must not re-run on render/state changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional single init
  }, []);

  const refreshOrders = useCallback(() => {
    void bootstrapOrdersRef.current();
  }, []);

  const markPaymentReceived = useCallback((orderId: string) => {
    if (!isAdminLocalOrderAuthorityEnabled()) {
      return;
    }
    paymentService.updatePaymentStatus(orderId, "paid");
  }, []);

  const markOrderProcessing = useCallback((orderId: string) => {
    if (!isAdminLocalOrderAuthorityEnabled()) {
      return;
    }
    const order = paymentService.getOrderById(orderId);
    if (!order) return;
    paymentService.updateOrderStatus(order.orderNumber, ORDER_STATUS.PROCESSING);
  }, []);

  const markOrderShipped = useCallback(async (orderId: string) => {
    if (!isAdminLocalOrderAuthorityEnabled()) {
      // Production: delivery advance only — order lifecycle syncs via Laravel.
      try {
        await advanceDelivery(orderId, DELIVERY_STATUS.SHIPPED);
      } catch {
        // Specialist engine owns errors.
      }
      if (typeof window !== "undefined") {
        window.dispatchEvent(new CustomEvent(DELIVERIES_UPDATED_EVENT));
      }
      return;
    }
    const order = paymentService.getOrderById(orderId);
    if (!order) return;
    paymentService.updateOrderStatus(order.orderNumber, ORDER_STATUS.SHIPPED);
    try {
      await advanceDelivery(orderId, DELIVERY_STATUS.SHIPPED);
    } catch {
      // Local order updated; server sync may catch up via order upsert.
    }
    if (typeof window !== "undefined") {
      window.dispatchEvent(new CustomEvent(DELIVERIES_UPDATED_EVENT));
    }
  }, []);

  const markOrderDelivered = useCallback(async (orderId: string) => {
    if (!isAdminLocalOrderAuthorityEnabled()) {
      try {
        await advanceDelivery(orderId, DELIVERY_STATUS.DELIVERED);
      } catch {
        // Specialist engine owns errors.
      }
      if (typeof window !== "undefined") {
        window.dispatchEvent(new CustomEvent(DELIVERIES_UPDATED_EVENT));
      }
      return;
    }
    const order = paymentService.getOrderById(orderId);
    if (!order) return;
    paymentService.updateOrderStatus(order.orderNumber, ORDER_STATUS.DELIVERED);
    try {
      await advanceDelivery(orderId, DELIVERY_STATUS.DELIVERED);
    } catch {
      // Local order updated; server sync may catch up via order upsert.
    }
    if (typeof window !== "undefined") {
      window.dispatchEvent(new CustomEvent(DELIVERIES_UPDATED_EVENT));
    }
  }, []);

  const assignDeliveryDriver = useCallback(async (orderId: string, driverName: string) => {
    await assignDeliveryDriverApi(orderId, driverName);
    if (typeof window !== "undefined") {
      window.dispatchEvent(new CustomEvent(DELIVERIES_UPDATED_EVENT));
    }
  }, []);

  const updateOrderStatus = useCallback((orderId: string, status: OrderStatus) => {
    if (!isAdminLocalOrderAuthorityEnabled()) {
      return;
    }
    const order = paymentService.getOrderById(orderId);
    if (!order) return;
    paymentService.updateOrderStatus(order.orderNumber, status);
  }, []);

  const bulkUpdateOrderStatus = useCallback(
    async (orderIds: string[], status: BulkOrderStatus) => {
      if (!isAdminLocalOrderAuthorityEnabled()) {
        throw new Error(
          "Local admin status updates are disabled. Use Laravel fulfillment, warehouse, or shipment queues.",
        );
      }
      if (orderIds.length === 0 || isBulkUpdating) {
        return;
      }

      const targetStatus = mapBulkOrderStatus(status);
      const previousOrders = ordersRef.current;
      const now = new Date().toISOString();

      setIsBulkUpdating(true);

      setOrders((current) => {
        const next = current.map((order) => {
          if (!orderIds.includes(order.id)) {
            return order;
          }
          if (order.status === ORDER_STATUS.CANCELLED) {
            return order;
          }
          return {
            ...order,
            status: targetStatus,
            updatedAt: now,
          };
        });
        ordersRef.current = next;
        return next;
      });

      for (const orderId of orderIds) {
        const order = paymentService.getOrderById(orderId);
        if (!order || order.status === ORDER_STATUS.CANCELLED) {
          continue;
        }
        paymentService.updateOrderStatus(order.orderNumber, targetStatus);
      }

      try {
        await bulkUpdateOrderStatusApi(orderIds, status);
        await bootstrapOrdersRef.current();
      } catch {
        ordersRef.current = previousOrders;
        setOrders(previousOrders);
        await bootstrapOrdersRef.current();
        throw new Error("Bulk order update failed. Changes were reverted.");
      } finally {
        setIsBulkUpdating(false);
      }
    },
    [isBulkUpdating],
  );

  const getOrder = useCallback(
    (orderNumber: string) => orders.find((order) => order.orderNumber === orderNumber),
    [orders],
  );

  const getOrderById = useCallback(
    (orderId: string) => findOrderById(orders, orderId),
    [orders],
  );

  const value = useMemo<AdminOrdersContextValue>(
    () => ({
      orders,
      isHydrated,
      isLive: wsConnected,
      wsConnected,
      realtimeTransport,
      lastSyncedAt,
      newOrderIds,
      refreshOrders,
      markPaymentReceived,
      markOrderProcessing,
      markOrderShipped,
      markOrderDelivered,
      assignDeliveryDriver,
      updateOrderStatus,
      bulkUpdateOrderStatus,
      isBulkUpdating,
      localOrderAuthorityEnabled: isAdminLocalOrderAuthorityEnabled(),
      getOrder,
      getOrderById,
    }),
    [
      orders,
      isHydrated,
      wsConnected,
      realtimeTransport,
      lastSyncedAt,
      newOrderIds,
      refreshOrders,
      markPaymentReceived,
      markOrderProcessing,
      markOrderShipped,
      markOrderDelivered,
      assignDeliveryDriver,
      updateOrderStatus,
      bulkUpdateOrderStatus,
      isBulkUpdating,
      getOrder,
      getOrderById,
    ],
  );

  return <AdminOrdersContext.Provider value={value}>{children}</AdminOrdersContext.Provider>;
}

export function useAdminOrders() {
  const ctx = useContext(AdminOrdersContext);
  if (!ctx) {
    throw new Error("useAdminOrders must be used within AdminOrdersProvider");
  }
  return ctx;
}
