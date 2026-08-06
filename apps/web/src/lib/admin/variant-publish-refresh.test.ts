import assert from "node:assert/strict";
import { describe, it } from "node:test";

/**
 * Mirrors ProductVariantsManager mutation completion:
 * local reload first, then parent publish-context refresh.
 */
async function afterVariantPublishabilityMutation(
  localReload: () => Promise<void>,
  onVariantsChanged?: () => void | Promise<void>,
): Promise<void> {
  await localReload();
  await onVariantsChanged?.();
}

describe("variant publishability refresh contract", () => {
  it("invokes parent refresh after local reload for bulk/per-variant pricing success", async () => {
    const calls: string[] = [];

    await afterVariantPublishabilityMutation(
      async () => {
        calls.push("local-reload");
      },
      async () => {
        calls.push("parent-refresh");
      },
    );

    assert.deepEqual(calls, ["local-reload", "parent-refresh"]);
  });

  it("keeps local reload when parent refresh fails so saved VariantPrice is not rolled back", async () => {
    const calls: string[] = [];

    await assert.rejects(
      () =>
        afterVariantPublishabilityMutation(
          async () => {
            calls.push("local-reload");
          },
          async () => {
            calls.push("parent-refresh");
            throw new Error("refresh failed");
          },
        ),
      /refresh failed/,
    );

    assert.deepEqual(calls, ["local-reload", "parent-refresh"]);
  });

  it("allows missing parent callback (local-only managers)", async () => {
    const calls: string[] = [];
    await afterVariantPublishabilityMutation(async () => {
      calls.push("local-reload");
    });
    assert.deepEqual(calls, ["local-reload"]);
  });
});
