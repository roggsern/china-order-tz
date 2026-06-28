export const NOTIFICATION_TYPE = {
  ORDER: "ORDER",
  PAYMENT: "PAYMENT",
  SHIPPING: "SHIPPING",
  SYSTEM: "SYSTEM",
} as const;

export type NotificationType =
  (typeof NOTIFICATION_TYPE)[keyof typeof NOTIFICATION_TYPE];

export type Notification = {
  id: string;
  userId: string;
  type: NotificationType;
  title: string;
  message: string;
  isRead: boolean;
  createdAt: string;
  /** Prevents duplicate notifications for the same event */
  dedupeKey?: string;
  orderId?: string;
  href?: string;
};

export type CreateNotificationInput = {
  userId: string;
  type: NotificationType;
  title: string;
  message: string;
  dedupeKey: string;
  orderId?: string;
  href?: string;
};

export type NotificationsListResponse = {
  notifications: Notification[];
  unreadCount: number;
};
