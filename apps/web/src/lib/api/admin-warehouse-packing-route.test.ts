import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";

const routePath = join(process.cwd(), "src/app/api/admin/warehouse/packing/route.ts");

describe("warehouse packing BFF route", () => {
  it("exposes GET and POST handlers that proxy to upstream warehouse packing", () => {
    const source = readFileSync(routePath, "utf8");

    assert.match(source, /export async function GET/);
    assert.match(source, /export async function POST/);
    assert.match(source, /await request\.json\(\)/);
    assert.match(source, /proxyAdminApiRequest\("\/warehouse\/packing", \{ method: "POST", body \}\)/);
  });
});
