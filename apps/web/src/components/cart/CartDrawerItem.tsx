"use client";

import Link from "next/link";
import type { CartLineItem } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { useCartActions } from "@/lib/cart/context";
import { useCartDrawer } from "@/lib/cart/drawer-context";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { MinusIcon, PlusIcon, TrashIcon } from "@/components/home/icons";
import { VariantLabel } from "@/components/catalog/VariantLabel";

interface CartDrawerItemProps {
  item: CartLineItem;
}

export function CartDrawerItem({ item }: CartDrawerItemProps) {
  const { updateQuantity, removeItem } = useCartActions();
  const { close } = useCartDrawer();
  const maxQuantity = Math.min(item.stock, 99);

  const decrease = () => updateQuantity(item.id, item.quantity - 1);
  const increase = () => updateQuantity(item.id, item.quantity + 1);

  return (
    <article className="flex gap-3 border-b border-zinc-100 py-4 last:border-b-0">
      <Link
        href={`/products/${item.slug}`}
        onClick={close}
        className="block shrink-0 overflow-hidden rounded-xl border border-zinc-100"
      >
        <ProductImageDisplay
          image={item.image}
          fallbackEmoji={item.image.emoji}
          fallbackGradient={item.image.gradient}
          className="h-20 w-20"
          emojiClassName="text-3xl"
        />
      </Link>

      <div className="min-w-0 flex-1">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <Link href={`/products/${item.slug}`} onClick={close}>
              <h3 className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900 transition hover:text-[#8b6914]">
                {item.name}
              </h3>
            </Link>
            <VariantLabel variant={item.variant} className="mt-0.5" />
          </div>

          <button
            type="button"
            onClick={() => removeItem(item.id)}
            className="shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-red-50 hover:text-red-600"
            aria-label={`Remove ${item.name} from cart`}
          >
            <TrashIcon className="h-4 w-4" />
          </button>
        </div>

        <p className="mt-1.5 text-sm font-bold text-red-600">{formatPrice(item.unitPrice)}</p>

        <div className="mt-2.5 flex items-center justify-between gap-2">
          <div className="inline-flex items-center rounded-xl border border-zinc-200 bg-zinc-50">
            <button
              type="button"
              onClick={decrease}
              className="flex h-8 w-8 items-center justify-center text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
              disabled={item.quantity <= 1}
              aria-label="Decrease quantity"
            >
              <MinusIcon className="h-3.5 w-3.5" />
            </button>
            <span className="flex h-8 min-w-[2rem] items-center justify-center border-x border-zinc-200 text-sm font-semibold tabular-nums text-zinc-900">
              {item.quantity}
            </span>
            <button
              type="button"
              onClick={increase}
              className="flex h-8 w-8 items-center justify-center text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
              disabled={item.quantity >= maxQuantity}
              aria-label="Increase quantity"
            >
              <PlusIcon className="h-3.5 w-3.5" />
            </button>
          </div>

          <p className="text-sm font-semibold tabular-nums text-zinc-900">
            {formatPrice(item.unitPrice * item.quantity)}
          </p>
        </div>
      </div>
    </article>
  );
}
