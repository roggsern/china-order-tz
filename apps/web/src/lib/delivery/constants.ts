/** Simulated in-transit delay after SHIPPED (ms). */
export const DELIVERY_IN_TRANSIT_SIMULATION_MS = Number.parseInt(
  process.env.DELIVERY_IN_TRANSIT_SIMULATION_MS ?? "45000",
  10,
);

export const DELIVERY_POLL_MS = 20_000;

export const DELIVERY_STATUS_LABELS: Record<string, string> = {
  PACKED: "Packed",
  SHIPPED: "Shipped",
  IN_TRANSIT: "In Transit",
  DELIVERED: "Delivered",
};
