"use client";

import {
  formatPurchaseQuantityAllowedExample,
  parsePurchaseQuantityInput,
  purchaseQuantityFormErrors,
} from "@/lib/admin/purchase-quantity-rules";

type PurchaseQuantityRulesEditorProps = {
  minimumOrderQuantity: number | null;
  orderIncrement: number | null;
  onMinimumOrderQuantityChange: (value: number | null) => void;
  onOrderIncrementChange: (value: number | null) => void;
  aggregatesVariants?: boolean;
  disabled?: boolean;
  minimumInputId?: string;
  incrementInputId?: string;
};

export function PurchaseQuantityRulesEditor({
  minimumOrderQuantity,
  orderIncrement,
  onMinimumOrderQuantityChange,
  onOrderIncrementChange,
  aggregatesVariants = false,
  disabled = false,
  minimumInputId = "purchase-quantity-minimum",
  incrementInputId = "purchase-quantity-increment",
}: PurchaseQuantityRulesEditorProps) {
  const errors = purchaseQuantityFormErrors(minimumOrderQuantity, orderIncrement);
  const allowedExample = formatPurchaseQuantityAllowedExample(
    minimumOrderQuantity,
    orderIncrement,
  );

  return (
    <section className="admin-card space-y-4 p-5">
      <div>
        <h2 className="text-sm font-semibold text-zinc-900">Purchase Quantity Rules</h2>
        <p className="mt-1 text-xs text-zinc-500">
          Control which quantities customers are allowed to purchase.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="admin-label" htmlFor={minimumInputId}>
            Minimum order quantity
          </label>
          <input
            id={minimumInputId}
            type="number"
            min={1}
            step={1}
            inputMode="numeric"
            disabled={disabled}
            value={minimumOrderQuantity ?? ""}
            onChange={(event) =>
              onMinimumOrderQuantityChange(parsePurchaseQuantityInput(event.target.value))
            }
            className="admin-input mt-1.5"
            placeholder="Unrestricted"
          />
          <p className="mt-1 text-xs text-zinc-500">
            The smallest total quantity of this product required to complete checkout.
          </p>
          {errors.minimumOrderQuantity ? (
            <p className="mt-1 text-xs text-red-600">{errors.minimumOrderQuantity}</p>
          ) : null}
        </div>
        <div>
          <label className="admin-label" htmlFor={incrementInputId}>
            Order increment
          </label>
          <input
            id={incrementInputId}
            type="number"
            min={1}
            step={1}
            inputMode="numeric"
            disabled={disabled}
            value={orderIncrement ?? ""}
            onChange={(event) =>
              onOrderIncrementChange(parsePurchaseQuantityInput(event.target.value))
            }
            className="admin-input mt-1.5"
            placeholder="Optional"
          />
          <p className="mt-1 text-xs text-zinc-500">
            Optional. After the minimum, allowed quantities increase by this amount.
          </p>
          {errors.orderIncrement ? (
            <p className="mt-1 text-xs text-red-600">{errors.orderIncrement}</p>
          ) : null}
        </div>
      </div>

      {aggregatesVariants ? (
        <p className="text-xs text-zinc-500">
          Different variants of this product count together toward the minimum and increment.
        </p>
      ) : null}

      {allowedExample ? (
        <p className="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700">
          {allowedExample}
        </p>
      ) : null}
    </section>
  );
}
