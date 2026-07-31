"use client";

import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import { isShippingAddressEmpty } from "@/lib/admin/order-detail-display";

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
  const addressMissing = isShippingAddressEmpty(shippingAddress);

  return (
    <div className="grid gap-6 sm:grid-cols-2">
      <div>
        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
          Customer
        </p>
        <div className="mt-3 space-y-1.5 text-sm">
          <p className="font-semibold text-zinc-900">{fullName || "—"}</p>
          {customer.email ? <p className="text-zinc-600">{customer.email}</p> : null}
          {customer.phone ? <p className="text-zinc-600">{customer.phone}</p> : null}
        </div>
      </div>

      <div>
        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
          Shipping Address
        </p>
        <div className="mt-3 text-sm text-zinc-600">
          {addressMissing ? (
            <p className="italic text-zinc-500">No shipping address snapshot available</p>
          ) : (
            <div className="space-y-1.5">
              {shippingAddress.addressLine1 ? <p>{shippingAddress.addressLine1}</p> : null}
              {shippingAddress.addressLine2 ? <p>{shippingAddress.addressLine2}</p> : null}
              {(shippingAddress.city || shippingAddress.region) && (
                <p>
                  {[shippingAddress.city, shippingAddress.region].filter(Boolean).join(", ")}
                </p>
              )}
              {shippingAddress.postalCode ? <p>{shippingAddress.postalCode}</p> : null}
              {shippingAddress.country ? <p>{shippingAddress.country}</p> : null}
            </div>
          )}
        </div>
      </div>

      {orderNotes ? (
        <div className="sm:col-span-2">
          <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
            Order Notes
          </p>
          <p className="mt-3 text-sm leading-relaxed text-zinc-600">{orderNotes}</p>
        </div>
      ) : null}
    </div>
  );
}
