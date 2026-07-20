const CUSTOMER_TOAST_STORAGE_KEY = "china-order-tz-customer-toast";

export const CUSTOMER_TOAST_EVENT = "customer-toast";

export type CustomerToastVariant = "success" | "info" | "wishlist" | "cart" | "error";

export type CustomerToastAction = {
  label: string;
  href?: string;
  onClickEvent?: "open-cart";
};

export type CustomerToastPayload = {
  text: string;
  title?: string;
  subtitle?: string;
  icon?: string;
  variant?: CustomerToastVariant;
  imageUrl?: string;
  actions?: CustomerToastAction[];
};

function normalizePayload(
  input: string | CustomerToastPayload,
): CustomerToastPayload {
  if (typeof input === "string") {
    return { text: input, variant: "success" };
  }
  return {
    variant: "success",
    ...input,
    text: input.text?.trim() || input.title || "Done",
  };
}

export function queueCustomerToast(input: string | CustomerToastPayload): void {
  if (typeof window === "undefined") {
    return;
  }

  const payload = normalizePayload(input);
  sessionStorage.setItem(CUSTOMER_TOAST_STORAGE_KEY, JSON.stringify(payload));
  window.dispatchEvent(new CustomEvent(CUSTOMER_TOAST_EVENT));
}

export function consumeQueuedCustomerToast(): CustomerToastPayload | null {
  if (typeof window === "undefined") {
    return null;
  }

  const raw = sessionStorage.getItem(CUSTOMER_TOAST_STORAGE_KEY);

  if (!raw) {
    return null;
  }

  sessionStorage.removeItem(CUSTOMER_TOAST_STORAGE_KEY);

  try {
    const parsed = JSON.parse(raw) as CustomerToastPayload;
    if (!parsed?.text && !parsed?.title) return null;
    return normalizePayload(parsed);
  } catch {
    return null;
  }
}

export function showCustomerToast(input: string | CustomerToastPayload): void {
  if (typeof window === "undefined") {
    return;
  }

  window.dispatchEvent(
    new CustomEvent<CustomerToastPayload>(CUSTOMER_TOAST_EVENT, {
      detail: normalizePayload(input),
    }),
  );
}

/** Rich product-added toast helper. */
export function showProductAddedToast(input: {
  productName: string;
  configurationLabel?: string;
  quantity?: number;
  imageUrl?: string;
}): void {
  const config = input.configurationLabel?.trim();
  showCustomerToast({
    variant: "cart",
    icon: "📱",
    title: "Product Added",
    text: input.productName,
    subtitle: config
      ? `${config}${input.quantity && input.quantity > 1 ? ` · Qty ${input.quantity}` : ""}`
      : input.quantity && input.quantity > 1
        ? `Qty ${input.quantity}`
        : "Successfully added to your cart.",
    imageUrl: input.imageUrl,
    actions: [
      { label: "View Cart", onClickEvent: "open-cart" },
      { label: "Continue Shopping", href: "/products" },
    ],
  });
}

export function showWishlistToast(added: boolean): void {
  showCustomerToast({
    variant: "wishlist",
    icon: "❤️",
    title: added ? "Added to Wishlist" : "Removed from Wishlist",
    text: added ? "Saved for later." : "Removed from your saved items.",
  });
}

export function showCartRemovedToast(productName?: string): void {
  showCustomerToast({
    variant: "cart",
    icon: "🗑",
    title: "Removed from Cart",
    text: productName ? `${productName} was removed.` : "Item removed from your cart.",
  });
}

export function showSavedToast(message = "Changes saved successfully"): void {
  showCustomerToast({
    variant: "success",
    icon: "✔",
    title: "Saved",
    text: message,
  });
}
