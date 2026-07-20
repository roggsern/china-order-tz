"use client";

import { useEffect, useState } from "react";
import { getRecentlyViewed } from "@/lib/catalog/recently-viewed";
import { ProductHorizontalScroll } from "./ProductHorizontalScroll";

interface RecentlyViewedProductsProps {
  currentProductId: number;
}

export function RecentlyViewedProducts({ currentProductId }: RecentlyViewedProductsProps) {
  const [items, setItems] = useState(() =>
    getRecentlyViewed().filter((item) => item.id !== currentProductId),
  );

  useEffect(() => {
    setItems(getRecentlyViewed().filter((item) => item.id !== currentProductId));
  }, [currentProductId]);

  if (items.length === 0) return null;

  return (
    <ProductHorizontalScroll
      title="Recently Viewed"
      subtitle="Customers also viewed"
      products={items}
    />
  );
}
