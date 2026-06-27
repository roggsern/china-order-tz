"use client";

import { useCart } from "@/lib/cart/context";
import { useCartDrawer } from "@/lib/cart/drawer-context";
import { CartIcon } from "@/components/home/icons";

interface CartIconButtonProps {
  className?: string;
  iconClassName?: string;
  showLabel?: boolean;
  labelClassName?: string;
  badgeClassName?: string;
}

const defaultBadgeClass =
  "absolute -right-2.5 -top-2 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#c9a227] px-1 text-[10px] font-bold text-zinc-900";

export function CartIconButton({
  className = "",
  iconClassName = "h-[18px] w-[18px]",
  showLabel = false,
  labelClassName = "",
  badgeClassName = defaultBadgeClass,
}: CartIconButtonProps) {
  const { totals, isHydrated } = useCart();
  const { open } = useCartDrawer();
  const cartCount = isHydrated ? totals.itemCount : 0;

  return (
    <button
      type="button"
      onClick={open}
      className={className}
      aria-label={`Open cart${cartCount > 0 ? `, ${cartCount} items` : ""}`}
    >
      <span className="relative shrink-0">
        <CartIcon className={iconClassName} />
        {cartCount > 0 && (
          <span className={badgeClassName}>{cartCount > 99 ? "99+" : cartCount}</span>
        )}
      </span>
      {showLabel && <span className={labelClassName}>Cart</span>}
    </button>
  );
}
