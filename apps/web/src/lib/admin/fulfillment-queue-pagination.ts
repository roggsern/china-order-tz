import type { FulfillmentJourneyFilter } from "@/lib/admin/fulfillment-operational";

export const FULFILLMENT_QUEUE_PAGE_SIZE = 20;

export type FulfillmentQueueServerFilters = {
  strategy?: string;
  status?: string;
};

export function mapJourneyFilterToStrategy(
  journey: FulfillmentJourneyFilter,
): string | undefined {
  if (journey === "china") {
    return "china";
  }
  if (journey === "local") {
    return "local";
  }
  return undefined;
}

export function mapStatusFilterToApi(status: string): string | undefined {
  return status === "all" ? undefined : status;
}

export function buildFulfillmentQueueServerFilters(input: {
  journey: FulfillmentJourneyFilter;
  status: string;
}): FulfillmentQueueServerFilters {
  return {
    strategy: mapJourneyFilterToStrategy(input.journey),
    status: mapStatusFilterToApi(input.status),
  };
}

export function clampPage(page: number, lastPage: number): number {
  if (!Number.isFinite(page) || page < 1) {
    return 1;
  }
  return Math.min(page, Math.max(1, lastPage));
}
