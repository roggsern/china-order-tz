"use client";

type ProductSimplePricingFieldsProps = {
  sellingPrice: number;
  costPrice: number | null;
  onSellingPriceChange: (value: number) => void;
  onCostPriceChange: (value: number | null) => void;
  sellingPriceId?: string;
  costPriceId?: string;
  disabled?: boolean;
};

export function ProductSimplePricingFields({
  sellingPrice,
  costPrice,
  onSellingPriceChange,
  onCostPriceChange,
  sellingPriceId = "product-selling-price",
  costPriceId = "product-cost-price",
  disabled = false,
}: ProductSimplePricingFieldsProps) {
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div>
        <label className="admin-label" htmlFor={sellingPriceId}>
          Selling price (TZS)
        </label>
        <input
          id={sellingPriceId}
          type="number"
          min={0}
          step={1}
          className="admin-input mt-1.5"
          value={sellingPrice || ""}
          disabled={disabled}
          onChange={(event) =>
            onSellingPriceChange(Number(event.target.value) || 0)
          }
        />
        <p className="mt-1 text-xs text-zinc-500">
          Required for simple products before activation. Variant products use per-variant pricing.
        </p>
      </div>
      <div>
        <label className="admin-label" htmlFor={costPriceId}>
          Cost / buying price (TZS)
        </label>
        <input
          id={costPriceId}
          type="number"
          min={0}
          step={1}
          className="admin-input mt-1.5"
          value={costPrice ?? ""}
          disabled={disabled}
          onChange={(event) => {
            const raw = event.target.value.trim();
            onCostPriceChange(raw === "" ? null : Number(raw) || 0);
          }}
        />
        <p className="mt-1 text-xs text-zinc-500">
          Optional product-level buying cost for margin reporting.
        </p>
      </div>
    </div>
  );
}
