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

/** Normalize timeline `step` into a stable progress key when `key` is absent from the API contract. */
function deriveProgressStepKey(step: string): string {
  const trimmed = step.trim();
  if (/^[A-Z][A-Z0-9_]+$/.test(trimmed)) {
    return trimmed;
  }

  return trimmed;
}

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

    if (entry.step) {
      progressSteps.push({
        key: deriveProgressStepKey(entry.step),
        step: entry.step,
        description: entry.description,
        completed: entry.completed,
        completed_at: entry.completed_at,
      });
    }
  }

  return { progressSteps, operationalEvents };
}
