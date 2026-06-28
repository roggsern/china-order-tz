/**
 * Standalone WebSocket server for admin order updates.
 * Run separately from Next.js for production (e.g. Railway, Fly.io, Docker sidecar).
 *
 * Usage: npx tsx ws/admin-orders-ws-server.ts
 */
import { createServer } from "node:http";
import { parse } from "node:url";
import { WebSocketServer } from "ws";
import { ADMIN_ORDERS_WS_PATH } from "../src/lib/admin/order-ws-types";
import { registerAdminOrderWsClient } from "../src/lib/admin/server/order-ws-broadcast";

const hostname = process.env.WS_HOSTNAME ?? "0.0.0.0";
const port = Number.parseInt(process.env.WS_PORT ?? process.env.PORT ?? "3001", 10);

const server = createServer((_req, res) => {
  res.writeHead(200, { "Content-Type": "text/plain" });
  res.end("Admin orders WebSocket server\n");
});

const wss = new WebSocketServer({ noServer: true });

wss.on("connection", (ws) => {
  registerAdminOrderWsClient(ws);
});

server.on("upgrade", (req, socket, head) => {
  const { pathname } = parse(req.url ?? "", true);

  if (pathname === ADMIN_ORDERS_WS_PATH) {
    wss.handleUpgrade(req, socket, head, (ws) => {
      wss.emit("connection", ws, req);
    });
    return;
  }

  socket.destroy();
});

server.listen(port, hostname, () => {
  console.log(`> Admin orders WebSocket server on ws://${hostname}:${port}${ADMIN_ORDERS_WS_PATH}`);
});
