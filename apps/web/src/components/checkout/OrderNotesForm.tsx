"use client";

import { CheckoutField, checkoutTextareaClass } from "./CheckoutField";

interface OrderNotesFormProps {
  value: string;
  onChange: (value: string) => void;
}

export function OrderNotesForm({ value, onChange }: OrderNotesFormProps) {
  return (
    <CheckoutField id="checkout-notes" label="Order notes">
      <textarea
        id="checkout-notes"
        rows={4}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder="Delivery instructions, gate code, preferred delivery time…"
        className={checkoutTextareaClass}
        maxLength={500}
      />
      <p className="mt-1.5 text-xs text-zinc-400">{value.length}/500 characters</p>
    </CheckoutField>
  );
}
