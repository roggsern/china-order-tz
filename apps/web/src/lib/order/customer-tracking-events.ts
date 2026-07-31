import type { CustomerTrackingPayload } from "@/lib/api/customer-tracking";

export type CustomerTrackingProgressStep = {
  key: string;
  step: string;
  description?: string | null;
  completed?: boolean;
  completed_at?: string | null;
};

export type CustomerTrackingOperationalEvent = {
  id: string;
  event_type: string;
  event_type_label: string;
  description?: string | null;
  location?: string | null;
  event_at?: string | null;
};

export function splitCustomerTrackingTimeline(timeline: CustomerTrackingPayload["timeline"]): {
  progressSteps: CustomerTrackingProgressStep[];
  operationalEvents: CustomerTrackingOperationalEvent[];
} {
  const progressSteps: CustomerTrackingProgressStep[] = [];
  const operationalEvents: CustomerTrackingOperationalEvent[] = [];

  for (const entry of timeline) {
    if (entry.event_type) {
      operationalEvents.push({
        id: entry.id ?? `${entry.event_type}-${entry.event_at ?? operationalEvents.length}`,
        event_type: entry.event_type,
        event_type_label: entry.event_type_label ?? entry.event_type,
        description: entry.description,
        location: entry.location,
        event_at: entry.event_at,
      });
      continue;
    }

    if (entry.key || entry.step) {
      progressSteps.push({
        key: entry.key ?? entry.step ?? "",
        step: entry.step ?? entry.key ?? "",
        description: entry.description,
        completed: entry.completed,
        completed_at: entry.completed_at,
      });
    }
  }

  return { progressSteps, operationalEvents };
}
