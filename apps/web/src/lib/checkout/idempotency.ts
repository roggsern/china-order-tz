const DRAFT_ORDER_MAP_KEY = "china-order-tz-draft-order-map";
const DRAFT_INFLIGHT_PREFIX = "china-order-tz-draft-inflight-";

type DraftOrderMap = Record<string, string>;

function readDraftOrderMap(): DraftOrderMap {
  if (typeof window === "undefined") {
    return {};
  }

  try {
    const raw = window.localStorage.getItem(DRAFT_ORDER_MAP_KEY);
    if (!raw) {
      return {};
    }

    const parsed = JSON.parse(raw) as DraftOrderMap;
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function writeDraftOrderMap(map: DraftOrderMap): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(DRAFT_ORDER_MAP_KEY, JSON.stringify(map));
}

export function linkDraftToOrder(draftId: string, orderId: string): void {
  const map = readDraftOrderMap();
  map[draftId] = orderId;
  writeDraftOrderMap(map);
}

export function getOrderIdForDraft(draftId: string): string | null {
  return readDraftOrderMap()[draftId] ?? null;
}

export function acquireDraftSubmissionLock(draftId: string): boolean {
  if (typeof window === "undefined") {
    return true;
  }

  const key = `${DRAFT_INFLIGHT_PREFIX}${draftId}`;
  if (window.sessionStorage.getItem(key) === "1") {
    return false;
  }

  window.sessionStorage.setItem(key, "1");
  return true;
}

export function releaseDraftSubmissionLock(draftId: string): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(`${DRAFT_INFLIGHT_PREFIX}${draftId}`);
}
