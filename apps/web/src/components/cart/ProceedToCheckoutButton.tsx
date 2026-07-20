"use client";

import { useRouter } from "next/navigation";
import { useCartState } from "@/lib/cart/context";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { Button } from "@/components/ui/Button";

interface ProceedToCheckoutButtonProps {
  className?: string;
  disabled?: boolean;
}

export function ProceedToCheckoutButton({
  className = "",
  disabled = false,
}: ProceedToCheckoutButtonProps) {
  const router = useRouter();
  const { items, isHydrated } = useCartState();

  const handleProceed = () => {
    if (items.length === 0) {
      return;
    }

    clearCheckoutDraft();
    router.push("/checkout");
  };

  return (
    <Button
      type="button"
      onClick={handleProceed}
      disabled={disabled || !isHydrated || items.length === 0}
      variant="primary"
      size="lg"
      fullWidth
      className={className || "mt-6"}
    >
      Proceed To Checkout
    </Button>
  );
}
