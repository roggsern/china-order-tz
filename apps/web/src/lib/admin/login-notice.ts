function getSessionStorage(): Storage | null {
  try {
    if (typeof globalThis === "undefined") {
      return null;
    }
    const storage = (globalThis as typeof globalThis & { sessionStorage?: Storage }).sessionStorage;
    return storage ?? null;
  } catch {
    return null;
  }
}

const ADMIN_LOGIN_NOTICE_KEY = "china-order-tz-admin-login-notice";

export function setAdminLoginNotice(message: string): void {
  getSessionStorage()?.setItem(ADMIN_LOGIN_NOTICE_KEY, message);
}

export function consumeAdminLoginNotice(): string | null {
  const storage = getSessionStorage();
  if (!storage) {
    return null;
  }
  const message = storage.getItem(ADMIN_LOGIN_NOTICE_KEY);
  if (message) {
    storage.removeItem(ADMIN_LOGIN_NOTICE_KEY);
  }
  return message;
}
