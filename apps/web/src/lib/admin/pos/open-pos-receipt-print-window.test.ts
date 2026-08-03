import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";
import {
  beginPosReceiptPrintWindow,
  completePosReceiptPrintWindow,
  resolvePosReceiptPrintPreviewUrl,
} from "@/lib/admin/pos/open-pos-receipt-print-window";

const root = join(process.cwd(), "src");

describe("POS receipt print window helper", () => {
  it("builds the authenticated receipt preview URL", () => {
    assert.equal(
      resolvePosReceiptPrintPreviewUrl("receipt-123", "thermal_80"),
      "/api/admin/pos/receipts/receipt-123/preview?layout=thermal_80",
    );
    assert.equal(
      resolvePosReceiptPrintPreviewUrl("receipt-456", "a4"),
      "/api/admin/pos/receipts/receipt-456/preview?layout=a4",
    );
  });

  it("opens a blank popup immediately and shows preparing state", () => {
    const events: string[] = [];
    const fakeDocument = {
      open() {
        events.push("document.open");
      },
      write(html: string) {
        events.push(`document.write:${html.includes("Preparing receipt") ? "loading" : "other"}`);
      },
      close() {
        events.push("document.close");
      },
    };

    const fakeWindow = {
      document: fakeDocument,
      focus() {
        events.push("focus");
      },
      location: { href: "" },
    } as unknown as Window;

    const originalWindow = globalThis.window;
    globalThis.window = {
      open: ((url: string | URL | undefined, target?: string, features?: string) => {
        events.push(`open:${String(url)}:${target}:${features ?? ""}`);
        return fakeWindow;
      }) as typeof window.open,
    } as Window & typeof globalThis;

    try {
      const win = beginPosReceiptPrintWindow();
      assert.strictEqual(win, fakeWindow);
    } finally {
      globalThis.window = originalWindow;
    }

    assert.deepEqual(events, [
      "open::_blank:width=420,height=720",
      "document.open",
      "document.write:loading",
      "document.close",
      "focus",
    ]);
  });

  it("throws when the browser blocks the print popup during begin", () => {
    const originalWindow = globalThis.window;
    globalThis.window = {
      open: (() => null) as typeof window.open,
    } as Window & typeof globalThis;

    try {
      assert.throws(
        () => beginPosReceiptPrintWindow(),
        /Unable to open receipt print window/,
      );
    } finally {
      globalThis.window = originalWindow;
    }
  });

  it("loads the preview URL after popup creation and prints after load", async () => {
    const events: string[] = [];
    const loadHandlers: Array<() => void> = [];

    const fakeWindow = {
      closed: false,
      location: { href: "" },
      focus() {
        events.push("focus");
      },
      print() {
        events.push("print");
      },
      addEventListener(type: string, handler: () => void) {
        if (type === "load") {
          loadHandlers.push(handler);
        }
      },
      get document() {
        return { readyState: "loading" as DocumentReadyState };
      },
    } as unknown as Window;

    const navigation = completePosReceiptPrintWindow(fakeWindow, "receipt-789", "thermal_80");
    assert.equal(
      fakeWindow.location.href,
      "/api/admin/pos/receipts/receipt-789/preview?layout=thermal_80",
    );

    loadHandlers[0]?.();
    await navigation;

    assert.deepEqual(events, ["focus", "print"]);
  });
});

describe("POS receipt print integration", () => {
  it("PosCashierPanel opens the popup before the async print audit call", () => {
    const source = readFileSync(
      join(root, "components/admin/pos/PosCashierPanel.tsx"),
      "utf8",
    );

    assert.match(source, /beginPosReceiptPrintWindow\(\)/);
    assert.match(source, /completePosReceiptPrintWindow\(printWindow/);
    assert.match(source, /printPosReceipt\(lastReceipt\.id, "thermal_80"\)/);

    const beginIndex = source.indexOf("beginPosReceiptPrintWindow()");
    const printIndex = source.indexOf('printPosReceipt(lastReceipt.id, "thermal_80")');
    assert.ok(beginIndex >= 0 && printIndex >= 0);
    assert.ok(beginIndex < printIndex);
    assert.doesNotMatch(source, /noopener/);
  });

  it("PosReceiptsManager opens the popup before the async print audit call", () => {
    const source = readFileSync(
      join(root, "components/admin/pos/PosReceiptsManager.tsx"),
      "utf8",
    );

    assert.match(source, /beginPosReceiptPrintWindow\(\)/);
    assert.match(source, /completePosReceiptPrintWindow\(printWindow/);
    assert.doesNotMatch(source, /openPrintWindow/);
    assert.doesNotMatch(source, /noopener/);

    const beginIndex = source.indexOf("beginPosReceiptPrintWindow()");
    const printIndex = source.indexOf("printPosReceipt(receipt.id, previewLayout)");
    assert.ok(beginIndex >= 0 && printIndex >= 0);
    assert.ok(beginIndex < printIndex);
  });

  it("does not inject receipt HTML with document.write in POS UI components", () => {
    const componentSources = [
      "components/admin/pos/PosCashierPanel.tsx",
      "components/admin/pos/PosReceiptsManager.tsx",
    ].map((relativePath) => readFileSync(join(root, relativePath), "utf8"));

    for (const source of componentSources) {
      assert.doesNotMatch(source, /document\.write/);
    }
  });
});
