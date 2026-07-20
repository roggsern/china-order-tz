export function resolveCustomerDisplayName(name?: string, email?: string): string {
  if (name?.trim()) {
    return name.trim();
  }

  if (email?.trim()) {
    const localPart = email.split("@")[0]?.trim();
    if (localPart) {
      return localPart;
    }
  }

  return "Customer";
}
