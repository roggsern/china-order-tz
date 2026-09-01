"use client";

import { useRouter } from "next/navigation";
import { useCartState } from "@/lib/cart/context";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { Button } from "@/components/ui/Button";
import { shouldBlockCheckoutCta } from "@/lib/purchasing/purchase-quantity";

interface ProceedToCheckoutButtonProps {
  className?: string;
  disabled?: boolean;
}

export function ProceedToCheckoutButton({
  className = "",
  disabled = false,
}: ProceedToCheckoutButtonProps) {
  const router = useRouter();
  const { items, isHydrated, purchaseQuantityBlockers } = useCartState();
  const quantityBlocked = shouldBlockCheckoutCta(purchaseQuantityBlockers);

  const handleProceed = () => {
    if (items.length === 0 || quantityBlocked) {
      return;
    }

    clearCheckoutDraft();
    router.push("/checkout");
  };

  return (
    <Button
      type="button"
      onClick={handleProceed}
      disabled={disabled || !isHydrated || items.length === 0 || quantityBlocked}
      title={
        quantityBlocked
          ? "Update quantities to meet purchase requirements before checkout."
          : undefined
      }
      variant="primary"
      size="lg"
      fullWidth
      className={className || "mt-6"}
    >
      Proceed To Checkout
    </Button>
  );
}
