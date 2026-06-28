import { ADMIN_ORDERS_WS_PATH } from "@/lib/admin/order-ws-types";

/** WebSocket path for admin order live updates. */
export { ADMIN_ORDERS_WS_PATH };

/** How long new-order row highlights stay visible (ms). */
export const ADMIN_NEW_ORDER_HIGHLIGHT_MS = 6_000;

/** Debounce client → server order sync (ms). */
export const ADMIN_ORDERS_SYNC_DEBOUNCE_MS = 150;

/** Initial WebSocket reconnect delay (ms). */
export const ADMIN_ORDERS_WS_INITIAL_RECONNECT_MS = 1_000;

/** Maximum WebSocket reconnect delay (ms). */
export const ADMIN_ORDERS_WS_MAX_RECONNECT_MS = 30_000;

/** Default admin order polling interval in production (ms). */
export const ADMIN_ORDERS_POLL_INTERVAL_MS = 5_000;
