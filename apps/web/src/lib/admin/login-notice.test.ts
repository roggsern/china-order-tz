import assert from "node:assert/strict";
import { test } from "node:test";
import {
  consumeAdminLoginNotice,
  setAdminLoginNotice,
} from "./login-notice";

test("admin login notice is set and consumed once", () => {
  const storage = new Map<string, string>();
  const originalSessionStorage = globalThis.sessionStorage;

  Object.defineProperty(globalThis, "sessionStorage", {
    configurable: true,
    value: {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => {
        storage.set(key, value);
      },
      removeItem: (key: string) => {
        storage.delete(key);
      },
    },
  });

  try {
    setAdminLoginNotice("Your account details have been updated. Please sign in again.");
    assert.equal(
      consumeAdminLoginNotice(),
      "Your account details have been updated. Please sign in again.",
    );
    assert.equal(consumeAdminLoginNotice(), null);
  } finally {
    Object.defineProperty(globalThis, "sessionStorage", {
      configurable: true,
      value: originalSessionStorage,
    });
  }
});
