import { getNmbCheckoutScriptUrl } from "@/lib/nmb/config";
import type {
  MpgsCheckoutConfigureInput,
  NmbHostedCheckoutCallbacks,
  NmbHostedCheckoutError,
} from "@/lib/nmb/types";

const SCRIPT_ID = "nmb-mpgs-checkout-sdk";

let scriptLoadPromise: Promise<void> | null = null;

function parseHostedCheckoutError(error: unknown): NmbHostedCheckoutError {
  if (error && typeof error === "object") {
    const record = error as Record<string, unknown>;
    return {
      cause: typeof record.cause === "string" ? record.cause : undefined,
      explanation: typeof record.explanation === "string" ? record.explanation : undefined,
      message: typeof record.message === "string" ? record.message : undefined,
    };
  }

  return {
    message: typeof error === "string" ? error : "NMB Hosted Checkout failed.",
  };
}

function formatHostedCheckoutError(error: NmbHostedCheckoutError): string {
  return error.explanation ?? error.message ?? error.cause ?? "NMB Hosted Checkout failed.";
}

function attachGlobalCallbacks(callbacks: NmbHostedCheckoutCallbacks): void {
  if (typeof window === "undefined") {
    return;
  }

  const host = window as Window & {
    completeCallback?: (resultIndicator: string, sessionVersion?: string) => void;
    cancelCallback?: () => void;
    errorCallback?: (error: unknown) => void;
    timeoutCallback?: () => void;
  };

  host.completeCallback = (resultIndicator: string, sessionVersion?: string) => {
    callbacks.onComplete?.(resultIndicator, sessionVersion);
  };

  host.cancelCallback = () => {
    callbacks.onCancel?.();
  };

  host.errorCallback = (error: unknown) => {
    callbacks.onError?.(parseHostedCheckoutError(error));
  };

  host.timeoutCallback = () => {
    callbacks.onTimeout?.();
  };
}

export function loadMpgsCheckoutScript(callbacks: NmbHostedCheckoutCallbacks): Promise<void> {
  if (typeof window === "undefined") {
    return Promise.reject(new Error("Hosted Checkout can only run in the browser."));
  }

  attachGlobalCallbacks(callbacks);

  if (window.Checkout) {
    return Promise.resolve();
  }

  if (scriptLoadPromise) {
    return scriptLoadPromise;
  }

  scriptLoadPromise = new Promise<void>((resolve, reject) => {
    const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;

    if (existing) {
      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener(
        "error",
        () => reject(new Error("Failed to load NMB Hosted Checkout SDK.")),
        { once: true },
      );
      return;
    }

    const script = document.createElement("script");
    script.id = SCRIPT_ID;
    script.src = getNmbCheckoutScriptUrl();
    script.async = true;
    script.setAttribute("data-error", "errorCallback");
    script.setAttribute("data-cancel", "cancelCallback");
    script.setAttribute("data-complete", "completeCallback");
    script.setAttribute("data-timeout", "timeoutCallback");

    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Failed to load NMB Hosted Checkout SDK."));

    document.body.appendChild(script);
  });

  return scriptLoadPromise;
}

export function configureMpgsHostedCheckout(config: MpgsCheckoutConfigureInput): void {
  if (!window.Checkout) {
    throw new Error("NMB Hosted Checkout SDK is not loaded.");
  }

  window.Checkout.configure(config);
}

export function showMpgsPaymentPage(): void {
  if (!window.Checkout) {
    throw new Error("NMB Hosted Checkout SDK is not loaded.");
  }

  window.Checkout.showPaymentPage();
}

export async function launchMpgsHostedCheckout(input: {
  sessionId: string;
  sessionVersion?: string | null;
  callbacks: NmbHostedCheckoutCallbacks;
}): Promise<void> {
  await loadMpgsCheckoutScript(input.callbacks);

  const config: MpgsCheckoutConfigureInput = {
    session: {
      id: input.sessionId,
    },
  };

  if (input.sessionVersion) {
    config.session.version = input.sessionVersion;
  }

  configureMpgsHostedCheckout(config);
  showMpgsPaymentPage();
}

export function describeHostedCheckoutError(error: NmbHostedCheckoutError): string {
  return formatHostedCheckoutError(error);
}
