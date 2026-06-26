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
import { ORDERS_STORAGE_KEY, ORDERS_UPDATED_EVENT } from "@/lib/payment/order-storage";
import { paymentService } from "@/lib/payment/PaymentService";

type AdminOrdersContextValue = {
  orders: Order[];
  isHydrated: boolean;
  refreshOrders: () => void;
  markPaymentReceived: (orderId: string) => void;
  markOrderShipped: (orderId: string) => void;
  markOrderDelivered: (orderId: string) => void;
  updateOrderStatus: (orderId: string, status: OrderStatus) => void;
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
  const initializedRef = useRef(false);

  const refreshOrders = useCallback(() => {
    setOrders(paymentService.listOrders());
  }, []);

  useEffect(() => {
    if (initializedRef.current) {
      return;
    }
    initializedRef.current = true;

    refreshOrders();
    setIsHydrated(true);

    const onStorage = (event: StorageEvent) => {
      if (event.key === ORDERS_STORAGE_KEY) {
        refreshOrders();
      }
    };

    const onOrdersUpdated = () => refreshOrders();

    window.addEventListener("storage", onStorage);
    window.addEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);
    return () => {
      window.removeEventListener("storage", onStorage);
      window.removeEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);
    };
  }, [refreshOrders]);

  const markPaymentReceived = useCallback(
    (orderId: string) => {
      paymentService.updatePaymentStatus(orderId, "paid");
      refreshOrders();
    },
    [refreshOrders],
  );

  const markOrderShipped = useCallback(
    (orderId: string) => {
      const order = paymentService.getOrderById(orderId);
      if (!order) return;
      paymentService.updateOrderStatus(order.orderNumber, ORDER_STATUS.SHIPPED);
      refreshOrders();
    },
    [refreshOrders],
  );

  const markOrderDelivered = useCallback(
    (orderId: string) => {
      const order = paymentService.getOrderById(orderId);
      if (!order) return;
      paymentService.updateOrderStatus(order.orderNumber, ORDER_STATUS.DELIVERED);
      refreshOrders();
    },
    [refreshOrders],
  );

  const updateOrderStatus = useCallback(
    (orderId: string, status: OrderStatus) => {
      const order = paymentService.getOrderById(orderId);
      if (!order) return;
      paymentService.updateOrderStatus(order.orderNumber, status);
      refreshOrders();
    },
    [refreshOrders],
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
      refreshOrders,
      markPaymentReceived,
      markOrderShipped,
      markOrderDelivered,
      updateOrderStatus,
      getOrder,
      getOrderById,
    }),
    [
      orders,
      isHydrated,
      refreshOrders,
      markPaymentReceived,
      markOrderShipped,
      markOrderDelivered,
      updateOrderStatus,
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
