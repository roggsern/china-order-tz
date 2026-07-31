import {
  computeQueueSummaryCards,
  mapAdminFulfillmentToQueueRow,
  type QueueSummaryKey,
} from "@/lib/admin/fulfillment-operational";
import {
  FULFILLMENT_QUEUE_PAGE_SIZE,
  type FulfillmentQueueServerFilters,
} from "@/lib/admin/fulfillment-queue-pagination";
import {
  fetchAdminFulfillmentsPage,
  type AdminFulfillment,
} from "@/lib/api/admin-fulfillments";

export async function aggregateQueueSummaryCards(
  filters: FulfillmentQueueServerFilters,
): Promise<ReturnType<typeof computeQueueSummaryCards>> {
  const template = computeQueueSummaryCards([]);
  const totals = Object.fromEntries(
    template.map((card) => [card.key, 0]),
  ) as Record<QueueSummaryKey, number>;

  let page = 1;
  let lastPage = 1;

  do {
    const result = await fetchAdminFulfillmentsPage({
      ...filters,
      page,
      perPage: FULFILLMENT_QUEUE_PAGE_SIZE,
    });
    lastPage = result.meta.last_page;
    const cards = computeQueueSummaryCards(
      result.items.map((row: AdminFulfillment) => mapAdminFulfillmentToQueueRow(row)),
    );
    for (const card of cards) {
      totals[card.key] += card.count;
    }
    page += 1;
  } while (page <= lastPage);

  return template.map((card) => ({
    ...card,
    count: totals[card.key],
  }));
}
