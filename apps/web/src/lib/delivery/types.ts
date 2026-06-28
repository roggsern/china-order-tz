/** Logistics delivery lifecycle (post-warehouse). */
export const DELIVERY_STATUS = {
  PACKED: "PACKED",
  SHIPPED: "SHIPPED",
  IN_TRANSIT: "IN_TRANSIT",
  DELIVERED: "DELIVERED",
} as const;

export type DeliveryStatus = (typeof DELIVERY_STATUS)[keyof typeof DELIVERY_STATUS];

export type DeliveryStageUpdatedBy = "system" | "admin";

export type DeliveryStageTimestamp = {
  status: DeliveryStatus;
  timestamp: string;
  updatedBy: DeliveryStageUpdatedBy;
};

export type Delivery = {
  deliveryId: string;
  orderId: string;
  orderNumber: string;
  status: DeliveryStatus;
  assignedDriver: string | null;
  stageTimestamps: DeliveryStageTimestamp[];
  createdAt: string;
  updatedAt: string;
};

export type AssignDeliveryInput = {
  orderId: string;
  driverName: string;
};

export type AdvanceDeliveryInput = {
  orderId: string;
  status: DeliveryStatus;
  updatedBy?: DeliveryStageUpdatedBy;
};
