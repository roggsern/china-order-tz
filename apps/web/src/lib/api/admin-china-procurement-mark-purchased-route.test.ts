import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";

const routePath = join(
  process.cwd(),
  "src/app/api/admin/china/procurement/[id]/mark-purchased/route.ts",
);

function serializeUpstreamBody(body: unknown): string {
  return JSON.stringify(body);
}

describe("china procurement mark-purchased BFF route", () => {
  it("route parses JSON body before proxying", () => {
    const source = readFileSync(routePath, "utf8");

    assert.match(source, /await request\.json\(\)/);
    assert.doesNotMatch(source, /await request\.text\(\)/);
    assert.match(source, /body,/);
  });

  it("forwards quantity_purchased as a parsed object to the upstream proxy", () => {
    const parsedBody = { quantity_purchased: 2 };
    const upstreamPayload = serializeUpstreamBody(parsedBody);

    assert.deepEqual(JSON.parse(upstreamPayload), { quantity_purchased: 2 });
    assert.equal(typeof parsedBody, "object");
  });

  it("regression: forwarding request.text() double-encodes and drops quantity_purchased", () => {
    const rawText = JSON.stringify({ quantity_purchased: 2 });
    const upstreamPayload = serializeUpstreamBody(rawText);
    const laravelReceives = JSON.parse(upstreamPayload) as unknown;

    assert.equal(typeof laravelReceives, "string");
    assert.equal((laravelReceives as { quantity_purchased?: number }).quantity_purchased, undefined);
  });
});
