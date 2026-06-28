import type { Delivery, DeliveryStatus } from "@/lib/delivery/types";

export async function fetchDeliveries(activeOnly = true): Promise<Delivery[]> {
  const response = await fetch(`/api/admin/deliveries?active=${activeOnly ? "1" : "0"}`, {
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error("Unable to load deliveries.");
  }

  const payload = (await response.json()) as { deliveries: Delivery[] };
  return payload.deliveries ?? [];
}

export async function fetchDelivery(orderId: string): Promise<Delivery | null> {
  const response = await fetch(`/api/admin/deliveries/${encodeURIComponent(orderId)}`, {
    cache: "no-store",
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error("Unable to load delivery.");
  }

  const payload = (await response.json()) as { delivery: Delivery };
  return payload.delivery;
}

export async function assignDeliveryDriver(orderId: string, driverName: string): Promise<Delivery> {
  const response = await fetch("/api/admin/deliveries/assign", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ orderId, driverName }),
  });

  if (!response.ok) {
    throw new Error("Unable to assign driver.");
  }

  const payload = (await response.json()) as { delivery: Delivery };
  return payload.delivery;
}

export async function advanceDelivery(orderId: string, status: DeliveryStatus): Promise<Delivery> {
  const response = await fetch("/api/admin/deliveries/advance", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ orderId, status }),
  });

  if (!response.ok) {
    throw new Error("Unable to update delivery status.");
  }

  const payload = (await response.json()) as { delivery: Delivery };
  return payload.delivery;
}
