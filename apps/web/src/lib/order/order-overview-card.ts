import type { OrderOverviewCardData } from "@/components/order/OrderOverviewCard";
import type { CustomerOrderListItem } from "@/lib/api/customer-orders";

export function mapCustomerOrderToOverviewCard(order: CustomerOrderListItem): OrderOverviewCardData {
  return {
    id: order.id,
    orderNumber: order.orderNumber,
    status: order.status,
    displayStatusLabel: order.displayStatusLabel,
    paymentStatus: order.paymentStatus,
    createdAt: order.createdAt,
    grandTotal: order.grandTotal,
    productName: order.itemPreview,
    quantity: order.itemCount,
    source: order.source,
    imageUrl: order.imageUrl,
    imageEmoji: "📦",
    imageGradient: "from-[#c9a227]/15 to-zinc-100",
    canPay: order.canPay,
  };
}
