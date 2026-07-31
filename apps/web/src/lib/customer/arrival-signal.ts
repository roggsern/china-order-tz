const JUST_REGISTERED_KEY = "china-order-tz-customer-just-registered";

export function markCustomerJustRegistered(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.setItem(JUST_REGISTERED_KEY, "1");
}

export function consumeCustomerJustRegistered(): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  const registered = window.sessionStorage.getItem(JUST_REGISTERED_KEY) === "1";
  if (registered) {
    window.sessionStorage.removeItem(JUST_REGISTERED_KEY);
  }

  return registered;
}

export function resolveAccountGreetingPrefix(options: {
  isLoggedIn: boolean;
  isFirstArrival: boolean;
}): "welcome" | "welcome_back" | "guest" {
  if (!options.isLoggedIn) {
    return "guest";
  }

  return options.isFirstArrival ? "welcome" : "welcome_back";
}
