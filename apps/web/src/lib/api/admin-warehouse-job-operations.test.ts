import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  buildWarehouseOperationalMaps,
  canCreatePackingForJob,
  canCreatePickListForJob,
  canOpenPackingForJob,
  canOpenPickListForJob,
  getPackingIdForJob,
  getPickListIdForJob,
} from "@/lib/api/admin-warehouse-job-operations";

const job = { id: "job-1", status: "picking" };

describe("warehouse job operational helpers", () => {
  it("builds pick list and packing maps by warehouse job id", () => {
    const maps = buildWarehouseOperationalMaps(
      [{ id: "pl-1", warehouse_job_id: "job-1" }],
      [{ id: "pk-1", warehouse_job_id: "job-2" }],
    );

    assert.equal(maps.pickListByJobId["job-1"], "pl-1");
    assert.equal(maps.packingByJobId["job-2"], "pk-1");
  });

  it("offers create pick list only for eligible pending/picking jobs without an existing list", () => {
    const emptyMaps = buildWarehouseOperationalMaps([], []);

    assert.equal(canCreatePickListForJob({ id: "job-1", status: "pending" }, emptyMaps, true), true);
    assert.equal(canCreatePickListForJob({ id: "job-1", status: "picking" }, emptyMaps, true), true);
    assert.equal(canCreatePickListForJob({ id: "job-1", status: "picked" }, emptyMaps, true), false);
    assert.equal(canCreatePickListForJob({ id: "job-1", status: "pending" }, emptyMaps, false), false);

    const withList = buildWarehouseOperationalMaps(
      [{ id: "pl-1", warehouse_job_id: "job-1" }],
      [],
    );
    assert.equal(canCreatePickListForJob(job, withList, true), false);
    assert.equal(canOpenPickListForJob(job, withList), true);
    assert.equal(getPickListIdForJob(job, withList), "pl-1");
  });

  it("offers create packing only for picked/packing jobs without an existing record", () => {
    const emptyMaps = buildWarehouseOperationalMaps([], []);

    assert.equal(canCreatePackingForJob({ id: "job-1", status: "picked" }, emptyMaps, true), true);
    assert.equal(canCreatePackingForJob({ id: "job-1", status: "packing" }, emptyMaps, true), true);
    assert.equal(canCreatePackingForJob({ id: "job-1", status: "picking" }, emptyMaps, true), false);
    assert.equal(canCreatePackingForJob({ id: "job-1", status: "picked" }, emptyMaps, false), false);

    const withRecord = buildWarehouseOperationalMaps(
      [],
      [{ id: "pk-1", warehouse_job_id: "job-1" }],
    );
    assert.equal(canCreatePackingForJob({ id: "job-1", status: "picked" }, withRecord, true), false);
    assert.equal(canOpenPackingForJob({ id: "job-1" }, withRecord), true);
    assert.equal(getPackingIdForJob({ id: "job-1" }, withRecord), "pk-1");
  });

  it("prevents duplicate create actions when operational maps already contain the job", () => {
    const maps = buildWarehouseOperationalMaps(
      [{ id: "pl-1", warehouse_job_id: "job-1" }],
      [{ id: "pk-1", warehouse_job_id: "job-1" }],
    );

    assert.equal(canCreatePickListForJob({ id: "job-1", status: "picking" }, maps, true), false);
    assert.equal(canCreatePackingForJob({ id: "job-1", status: "picked" }, maps, true), false);
    assert.equal(canOpenPickListForJob({ id: "job-1" }, maps), true);
    assert.equal(canOpenPackingForJob({ id: "job-1" }, maps), true);
  });
});

describe("warehouse create API clients", () => {
  it("creates a pick list for a warehouse job", async () => {
    const originalFetch = globalThis.fetch;
    let requestUrl = "";
    let requestBody: unknown;

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      requestUrl = String(input);
      requestBody = init?.body ? JSON.parse(String(init.body)) : null;
      return Response.json({
        success: true,
        data: {
          id: "pl-new",
          warehouse_job_id: "job-1",
          order_id: "ord-1",
          status: "pending",
        },
      });
    }) as typeof fetch;

    try {
      const { createWarehousePickList } = await import("@/lib/api/admin-warehouse-operations");
      const pickList = await createWarehousePickList("job-1");

      assert.match(requestUrl, /\/api\/admin\/warehouse\/pick-lists$/);
      assert.deepEqual(requestBody, { warehouse_job_id: "job-1" });
      assert.equal(pickList.id, "pl-new");
      assert.equal(pickList.warehouse_job_id, "job-1");
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("creates a packing record for a warehouse job", async () => {
    const originalFetch = globalThis.fetch;
    let requestUrl = "";
    let requestBody: unknown;

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      requestUrl = String(input);
      requestBody = init?.body ? JSON.parse(String(init.body)) : null;
      return Response.json({
        success: true,
        data: {
          id: "pk-new",
          warehouse_job_id: "job-1",
          status: "pending",
        },
      });
    }) as typeof fetch;

    try {
      const { createWarehousePacking } = await import("@/lib/api/admin-warehouse-operations");
      const record = await createWarehousePacking("job-1");

      assert.match(requestUrl, /\/api\/admin\/warehouse\/packing$/);
      assert.deepEqual(requestBody, { warehouse_job_id: "job-1" });
      assert.equal(record.id, "pk-new");
      assert.equal(record.warehouse_job_id, "job-1");
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("surfaces validation errors from create pick list", async () => {
    const originalFetch = globalThis.fetch;

    globalThis.fetch = mock.fn(async () =>
      Response.json(
        { success: false, message: "Pick list already exists for this job." },
        { status: 422 },
      ),
    ) as typeof fetch;

    try {
      const { createWarehousePickList, AdminWarehouseOpsApiError } = await import(
        "@/lib/api/admin-warehouse-operations"
      );

      await assert.rejects(
        () => createWarehousePickList("job-1"),
        (error: unknown) => {
          assert.ok(error instanceof AdminWarehouseOpsApiError);
          assert.match(error.message, /already exists/i);
          assert.equal(error.statusCode, 422);
          return true;
        },
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
