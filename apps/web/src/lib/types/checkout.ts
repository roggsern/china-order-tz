export type CustomerInformation = {
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
};

export type ShippingAddress = {
  addressLine1: string;
  addressLine2: string;
  city: string;
  region: string;
  postalCode: string;
  country: string;
};

export type CheckoutFormData = {
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes: string;
};

export const EMPTY_CUSTOMER: CustomerInformation = {
  firstName: "",
  lastName: "",
  email: "",
  phone: "",
};

export const EMPTY_SHIPPING_ADDRESS: ShippingAddress = {
  addressLine1: "",
  addressLine2: "",
  city: "",
  region: "",
  postalCode: "",
  country: "Tanzania",
};

export const EMPTY_CHECKOUT_FORM: CheckoutFormData = {
  customer: EMPTY_CUSTOMER,
  shippingAddress: EMPTY_SHIPPING_ADDRESS,
  orderNotes: "",
};

export type CheckoutFormErrors = {
  customer?: Partial<Record<keyof CustomerInformation, string>>;
  shippingAddress?: Partial<Record<keyof ShippingAddress, string>>;
};
