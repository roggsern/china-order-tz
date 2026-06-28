import { createServer } from "node:http";
import { parse } from "node:url";
import next from "next";
import { WebSocketServer } from "ws";
import { ADMIN_ORDERS_WS_PATH } from "./src/lib/admin/order-ws-types";
import { registerAdminOrderWsClient } from "./src/lib/admin/server/order-ws-broadcast";
import { ORDER_TRACKING_WS_PATH } from "./src/lib/order/order-tracking-ws-types";
import { registerOrderTrackingWsClient } from "./src/lib/order/server/order-tracking-broadcast";
import { NOTIFICATIONS_WS_PATH } from "./src/lib/notifications/notification-ws-types";
import { registerNotificationWsClient } from "./src/lib/notifications/server/notification-broadcast";
import { normalizeUserId } from "./src/lib/notifications/user-id";

/**
 * Local development server with embedded WebSocket support.
 * Not used on Vercel — production uses `next start` with polling fallback.
 * For self-hosted WebSocket in production, run ws/admin-orders-ws-server.ts separately
 * and set NEXT_PUBLIC_ADMIN_WS_URL to that endpoint.
 */
const dev = process.env.NODE_ENV !== "production";
const hostname = process.env.HOSTNAME ?? "localhost";
const port = Number.parseInt(process.env.PORT ?? "3000", 10);

const app = next({ dev, hostname, port });
const handle = app.getRequestHandler();

app.prepare().then(() => {
  const server = createServer((req, res) => {
    const parsedUrl = parse(req.url ?? "", true);
    handle(req, res, parsedUrl);
  });

  const adminWss = new WebSocketServer({ noServer: true });
  const trackingWss = new WebSocketServer({ noServer: true });
  const notificationsWss = new WebSocketServer({ noServer: true });

  adminWss.on("connection", (ws) => {
    registerAdminOrderWsClient(ws);
  });

  trackingWss.on("connection", (ws, request) => {
    const { query } = parse(request.url ?? "", true);
    const orderId = typeof query.orderId === "string" ? query.orderId : "";
    if (!orderId) {
      ws.close();
      return;
    }
    registerOrderTrackingWsClient(ws, orderId);
  });

  notificationsWss.on("connection", (ws, request) => {
    const { query } = parse(request.url ?? "", true);
    const userId = typeof query.userId === "string" ? normalizeUserId(query.userId) : "";
    if (!userId) {
      ws.close();
      return;
    }
    registerNotificationWsClient(ws, userId);
  });

  server.on("upgrade", (req, socket, head) => {
    const { pathname, query } = parse(req.url ?? "", true);

    if (pathname === ADMIN_ORDERS_WS_PATH) {
      adminWss.handleUpgrade(req, socket, head, (ws) => {
        adminWss.emit("connection", ws, req);
      });
      return;
    }

    if (pathname === ORDER_TRACKING_WS_PATH) {
      const orderId = typeof query.orderId === "string" ? query.orderId : "";
      if (!orderId) {
        socket.destroy();
        return;
      }
      trackingWss.handleUpgrade(req, socket, head, (ws) => {
        trackingWss.emit("connection", ws, req);
      });
      return;
    }

    if (pathname === NOTIFICATIONS_WS_PATH) {
      const userId = typeof query.userId === "string" ? query.userId : "";
      if (!userId) {
        socket.destroy();
        return;
      }
      notificationsWss.handleUpgrade(req, socket, head, (ws) => {
        notificationsWss.emit("connection", ws, req);
      });
      return;
    }

    socket.destroy();
  });

  server.listen(port, () => {
    console.log(`> Next.js ready on http://${hostname}:${port}`);
    console.log(`> Admin orders WebSocket: ws://${hostname}:${port}${ADMIN_ORDERS_WS_PATH}`);
    console.log(`> Order tracking WebSocket: ws://${hostname}:${port}${ORDER_TRACKING_WS_PATH}?orderId=...`);
    console.log(`> Notifications WebSocket: ws://${hostname}:${port}${NOTIFICATIONS_WS_PATH}?userId=...`);
  });
});
