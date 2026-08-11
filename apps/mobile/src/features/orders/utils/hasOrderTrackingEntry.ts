import type { OrderShipmentSummary } from '../models/types';

/**
 * Show tracking entry only when server provided shipment/tracking signals.
 * Never invent tracking eligibility.
 */
export function hasOrderTrackingEntry(order: {
  shipment?: OrderShipmentSummary | null;
}): boolean {
  const shipment = order.shipment;
  if (!shipment) return false;
  return Boolean(
    shipment.status ||
      shipment.statusLabel ||
      shipment.trackingReference ||
      shipment.carrierName,
  );
}
