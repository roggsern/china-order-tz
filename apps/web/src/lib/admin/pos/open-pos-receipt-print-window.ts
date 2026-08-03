import { posReceiptPreviewUrl } from "@/lib/api/admin-pos";

const RECEIPT_PRINT_WINDOW_FEATURES = "width=420,height=720";

const RECEIPT_PRINT_LOADING_HTML = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Preparing receipt...</title>
<style>
  body {
    font-family: "Segoe UI", system-ui, sans-serif;
    display: grid;
    place-items: center;
    min-height: 100vh;
    margin: 0;
    background: #f4f4f4;
    color: #333;
  }
</style>
</head>
<body><p>Preparing receipt&hellip;</p></body>
</html>`;

export function resolvePosReceiptPrintPreviewUrl(
  receiptId: string,
  layout = "thermal_80",
): string {
  return posReceiptPreviewUrl(receiptId, layout);
}

function waitForPrintWindowLoad(win: Window): Promise<void> {
  return new Promise((resolve, reject) => {
    const finish = () => resolve();
    const fail = () => reject(new Error("Receipt preview failed to load."));

    try {
      if (win.document.readyState === "complete") {
        finish();
        return;
      }
    } catch {
      // Preview navigation may temporarily restrict document access.
    }

    win.addEventListener("load", finish, { once: true });
    win.addEventListener("error", fail, { once: true });
  });
}

/**
 * Opens the print popup synchronously during the user click handler.
 * Must be called before any await so browsers do not block the popup.
 */
export function beginPosReceiptPrintWindow(): Window {
  const win = window.open("", "_blank", RECEIPT_PRINT_WINDOW_FEATURES);

  if (!win) {
    throw new Error("Unable to open receipt print window. Check popup blocker settings.");
  }

  win.document.open();
  win.document.write(RECEIPT_PRINT_LOADING_HTML);
  win.document.close();
  win.focus();

  return win;
}

/**
 * Navigates an already-open print popup to the receipt preview and triggers print.
 */
export async function completePosReceiptPrintWindow(
  win: Window,
  receiptId: string,
  layout = "thermal_80",
): Promise<void> {
  const url = resolvePosReceiptPrintPreviewUrl(receiptId, layout);
  const navigation = waitForPrintWindowLoad(win);
  win.location.href = url;
  await navigation;
  win.focus();
  win.print();
}

/**
 * Convenience wrapper when no backend audit call is needed before navigation.
 */
export async function openPosReceiptPrintWindow(
  receiptId: string,
  layout = "thermal_80",
): Promise<void> {
  const win = beginPosReceiptPrintWindow();
  await completePosReceiptPrintWindow(win, receiptId, layout);
}
