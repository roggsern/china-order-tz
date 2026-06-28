export function normalizeUserId(userId: string): string {
  return userId.trim().toLowerCase();
}

export function resolveOrderCustomerUserId(order: {
  customer?: { email?: string | null } | null;
}): string | null {
  const email = order.customer?.email?.trim();
  if (!email) {
    return null;
  }
  return normalizeUserId(email);
}
