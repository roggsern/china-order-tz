type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type AdminShipment = {
  id: string;
  order_id: string;
  fulfillment_id?: string | null;
  shipment_number: string;
  transport_mode: string;
  transport_mode_label?: string | null;
  status: string;
  status_label?: string | null;
  carrier_name?: string | null;
  tracking_reference?: string | null;
  origin?: string | null;
  destination?: string | null;
  booked_at?: string | null;
  shipped_at?: string | null;
  delivered_at?: string | null;
  notes?: string | null;
  order?: {
    id: string;
    order_number: string;
    status?: string;
    customer?: { id: string; name: string; email: string } | null;
  } | null;
};

export type ShipmentEligibility = {
  eligible: boolean;
  reason?: string | null;
  transport_mode?: string | null;
  delivery_type?: string | null;
  shipment?: AdminShipment | null;
};

export class AdminShipmentApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminShipmentApiError";
  }
}

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) return first.trim();
  }
  return fallback;
}

async function adminFetch<T>(path: string, init: RequestInit, fallback: string): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminShipmentApiError(formatError(payload, fallback), response.status);
  }
  return payload.data as T;
}

export async function fetchAdminShipments(params?: {
  status?: string;
  transportMode?: string;
  orderId?: string;
}): Promise<AdminShipment[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set("status", params.status);
  if (params?.transportMode) search.set("transport_mode", params.transportMode);
  if (params?.orderId) search.set("order_id", params.orderId);
  search.set("per_page", "50");
  const qs = search.toString();
  const data = await adminFetch<AdminShipment[]>(
    `/api/admin/shipments${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load shipments.",
  );
  return Array.isArray(data) ? data : [];
}

export async function fetchShipmentEligibility(
  fulfillmentId: string,
): Promise<ShipmentEligibility> {
  return adminFetch<ShipmentEligibility>(
    `/api/admin/fulfillments/${encodeURIComponent(fulfillmentId)}/shipment-eligibility`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to check shipment eligibility.",
  );
}

export async function createAdminShipment(
  fulfillmentId: string,
  body?: { carrier_name?: string; notes?: string },
): Promise<AdminShipment> {
  return adminFetch<AdminShipment>(
    `/api/admin/shipments/create/${encodeURIComponent(fulfillmentId)}`,
    {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body ?? {}),
    },
    "Unable to create shipment.",
  );
}

export async function updateAdminShipmentStatus(
  shipmentId: string,
  body: { carrier_name?: string | null; notes?: string | null },
): Promise<AdminShipment> {
  return adminFetch<AdminShipment>(
    `/api/admin/shipments/${encodeURIComponent(shipmentId)}/status`,
    {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to update shipment metadata.",
  );
}

export type TrackingTimelineItem = {
  id?: string | null;
  event_type: string;
  event_type_label?: string | null;
  description?: string | null;
  location?: string | null;
  event_at?: string | null;
};

export type ShipmentTrackingPayload = {
  shipment?: AdminShipment;
  current_status: string;
  current_status_label?: string | null;
  timeline: TrackingTimelineItem[];
};

export async function fetchAdminShipmentTracking(
  shipmentId: string,
): Promise<ShipmentTrackingPayload> {
  return adminFetch<ShipmentTrackingPayload>(
    `/api/admin/shipments/${encodeURIComponent(shipmentId)}/tracking`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load tracking timeline.",
  );
}

export async function postAdminTrackingEvent(
  shipmentId: string,
  body: {
    event_type: string;
    description?: string | null;
    location?: string | null;
    event_at?: string | null;
  },
): Promise<ShipmentTrackingPayload & { event?: TrackingTimelineItem }> {
  return adminFetch(
    `/api/admin/shipments/${encodeURIComponent(shipmentId)}/tracking`,
    {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to record tracking event.",
  );
}

export async function confirmNegotiatedDelivery(orderId: string): Promise<unknown> {
  return adminFetch(
    `/api/admin/orders/${encodeURIComponent(orderId)}/delivery-option/confirm-negotiated`,
    { method: "POST", headers: { Accept: "application/json" } },
    "Unable to confirm negotiated delivery.",
  );
}
