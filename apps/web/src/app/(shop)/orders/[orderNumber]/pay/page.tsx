"use client";

import { useParams } from "next/navigation";
import { OrderPayContent } from "@/components/order/OrderPayContent";

export default function OrderPayPage() {
  const params = useParams<{ orderNumber: string }>();
  const orderNumber = decodeURIComponent(params.orderNumber ?? "");

  return <OrderPayContent orderNumber={orderNumber} />;
}
