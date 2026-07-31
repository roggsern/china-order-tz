import type { ShippingMethodCode } from "@/lib/shipping/types";

export type ShippingDurationWindow = {
  min_days: number;
  max_days: number;
  typical_days: number;
  method_code: string;
  source: string;
};

export type ShippingDurationsPayload = {
  air: ShippingDurationWindow;
  sea: ShippingDurationWindow;
  local: ShippingDurationWindow;
};

type DurationsCache = ShippingDurationsPayload;

let cache: DurationsCache | null = null;
let inflight: Promise<DurationsCache | null> | null = null;

const METHOD_KEY: Record<ShippingMethodCode, keyof DurationsCache> = {
  air_freight: "air",
  sea_freight: "sea",
  local_delivery: "local",
};

/**
 * Last-resort windows when `/shipping/durations` is unavailable.
 * Must stay aligned with backend ShippingDurationResolver defaults — not a business SSoT.
 */
export const DEFENSIVE_DURATION_FALLBACKS: ShippingDurationsPayload = {
  air: {
    min_days: 7,
    max_days: 12,
    typical_days: 10,
    method_code: "air_freight",
    source: "defensive_fallback",
  },
  sea: {
    min_days: 35,
    max_days: 45,
    typical_days: 40,
    method_code: "sea_freight",
    source: "defensive_fallback",
  },
  local: {
    min_days: 1,
    max_days: 5,
    typical_days: 2,
    method_code: "local_delivery",
    source: "defensive_fallback",
  },
};

export function formatDurationWindow(minDays: number, maxDays: number): string {
  if (!Number.isFinite(minDays) || !Number.isFinite(maxDays)) return "—";
  if (minDays === maxDays) return String(minDays);
  return `${minDays}–${maxDays}`;
}

export function formatEstimatedDeliveryLabel(minDays: number, maxDays: number): string {
  const window = formatDurationWindow(minDays, maxDays);
  if (!window || window === "—") return "—";
  return `${window} days`;
}

/** Customer-facing PDP label e.g. "7–12 Days". */
export function formatDurationDaysLabel(window: Pick<ShippingDurationWindow, "min_days" | "max_days">): string {
  const base = formatDurationWindow(window.min_days, window.max_days);
  if (!base || base === "—") return "—";
  return `${base} Days`;
}

export function durationDaysFromSnapshots(
  minDays: number | string | null | undefined,
  maxDays: number | string | null | undefined,
): string {
  const min = typeof minDays === "number" ? minDays : Number.parseInt(String(minDays ?? ""), 10);
  const max = typeof maxDays === "number" ? maxDays : Number.parseInt(String(maxDays ?? ""), 10);
  if (!Number.isFinite(min) || !Number.isFinite(max)) return "—";
  return formatDurationWindow(min, max);
}

export function getCachedShippingDurations(): DurationsCache | null {
  return cache;
}

export function setCachedShippingDurations(payload: DurationsCache | null): void {
  cache = payload;
}

export function getCachedDurationWindow(methodCode: ShippingMethodCode): ShippingDurationWindow | null {
  const key = METHOD_KEY[methodCode];
  return cache?.[key] ?? null;
}

/** Prefer API cache; otherwise defensive fallback. */
export function resolveDurationWindow(methodCode: ShippingMethodCode): ShippingDurationWindow {
  const cached = getCachedDurationWindow(methodCode);
  if (cached) return cached;
  return DEFENSIVE_DURATION_FALLBACKS[METHOD_KEY[methodCode]];
}

function parseWindow(raw: unknown): ShippingDurationWindow | null {
  if (!raw || typeof raw !== "object") return null;
  const row = raw as Record<string, unknown>;
  const min = Number(row.min_days);
  const max = Number(row.max_days);
  const typical = Number(row.typical_days);
  if (!Number.isFinite(min) || !Number.isFinite(max)) return null;
  return {
    min_days: min,
    max_days: max,
    typical_days: Number.isFinite(typical) ? typical : Math.round((min + max) / 2),
    method_code: String(row.method_code ?? ""),
    source: String(row.source ?? "shipping_rates"),
  };
}

export async function fetchShippingDurations(): Promise<DurationsCache | null> {
  if (cache) return cache;
  if (inflight) return inflight;

  inflight = (async () => {
    try {
      const response = await fetch("/api/v1/shipping/durations", {
        method: "GET",
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      if (!response.ok) return null;
      const json = (await response.json()) as { data?: Record<string, unknown> };
      const air = parseWindow(json.data?.air);
      const sea = parseWindow(json.data?.sea);
      const local = parseWindow(json.data?.local);
      if (!air || !sea || !local) return null;
      cache = { air, sea, local };
      return cache;
    } catch {
      return null;
    } finally {
      inflight = null;
    }
  })();

  return inflight;
}

/** Test helper — clears module cache between unit tests. */
export function resetShippingDurationsCacheForTests(): void {
  cache = null;
  inflight = null;
}
