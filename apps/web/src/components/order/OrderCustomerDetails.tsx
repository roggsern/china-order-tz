"use client";

import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";

interface OrderCustomerDetailsProps {
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes?: string;
}

export function OrderCustomerDetails({
  customer,
  shippingAddress,
  orderNotes,
}: OrderCustomerDetailsProps) {
  const fullName = `${customer.firstName} ${customer.lastName}`.trim();

  return (
    <div className="grid gap-6 sm:grid-cols-2">
      <div>
        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
          Customer
        </p>
        <div className="mt-3 space-y-1.5 text-sm">
          <p className="font-semibold text-zinc-900">{fullName}</p>
          <p className="text-zinc-600">{customer.email}</p>
          <p className="text-zinc-600">{customer.phone}</p>
        </div>
      </div>

      <div>
        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
          Shipping Address
        </p>
        <div className="mt-3 space-y-1.5 text-sm text-zinc-600">
          <p>{shippingAddress.addressLine1}</p>
          {shippingAddress.addressLine2 && <p>{shippingAddress.addressLine2}</p>}
          <p>
            {shippingAddress.city}, {shippingAddress.region}
          </p>
          {shippingAddress.postalCode && <p>{shippingAddress.postalCode}</p>}
          <p>{shippingAddress.country}</p>
        </div>
      </div>

      {orderNotes && (
        <div className="sm:col-span-2">
          <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Order Notes
          </p>
          <p className="mt-3 text-sm leading-relaxed text-zinc-600">{orderNotes}</p>
        </div>
      )}
    </div>
  );
}
