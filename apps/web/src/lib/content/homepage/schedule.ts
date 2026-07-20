import type { AdvertisementStatus } from "./types";

type Schedulable = {
  status: AdvertisementStatus;
  displayStart: string;
  displayEnd: string;
  priority: number;
};

export function isWithinDisplayWindow(
  item: Pick<Schedulable, "displayStart" | "displayEnd">,
  now = new Date(),
): boolean {
  const start = Date.parse(item.displayStart);
  const end = Date.parse(item.displayEnd);
  if (Number.isNaN(start) || Number.isNaN(end)) {
    return false;
  }
  const ts = now.getTime();
  return ts >= start && ts <= end;
}

export function isActivelyScheduled(item: Schedulable, now = new Date()): boolean {
  return item.status === "active" && isWithinDisplayWindow(item, now);
}

export function sortByPriorityDesc<T extends { priority: number }>(items: T[]): T[] {
  return [...items].sort((a, b) => b.priority - a.priority);
}

export function filterActiveScheduled<T extends Schedulable>(
  items: T[],
  now = new Date(),
): T[] {
  return sortByPriorityDesc(items.filter((item) => isActivelyScheduled(item, now)));
}
