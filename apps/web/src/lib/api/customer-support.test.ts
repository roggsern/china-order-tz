import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  CUSTOMER_SUPPORT_CATEGORIES,
  formatSupportApiError,
  isSupportTicketClosed,
  mapSupportTicketFromResponse,
  mapSupportTicketsFromResponse,
  normalizeCreateSupportTicketInput,
  type CustomerSupportTicket,
} from "./customer-support.ts";

describe("customer support api helpers", () => {
  it("maps ticket list responses and preserves empty arrays", () => {
    const ticket: CustomerSupportTicket = {
      id: "ticket-1",
      ticket_number: "SUP-001",
      subject: "Missing item",
      category: "order_issue",
      category_label: "Order issue",
      priority: "normal",
      status: "open",
      status_label: "Open",
      order_id: null,
      created_at: "2026-07-30T00:00:00.000Z",
      updated_at: "2026-07-30T00:00:00.000Z",
    };

    assert.deepEqual(
      mapSupportTicketsFromResponse({ success: true, data: [ticket] }),
      [ticket],
    );
    assert.deepEqual(mapSupportTicketsFromResponse({ success: true, data: [] }), []);
    assert.deepEqual(mapSupportTicketsFromResponse({ success: true }), []);
  });

  it("maps single ticket responses", () => {
    const ticket: CustomerSupportTicket = {
      id: "ticket-2",
      ticket_number: "SUP-002",
      subject: "Payment question",
      category: "payment_issue",
      category_label: "Payment issue",
      priority: "normal",
      status: "waiting_customer",
      status_label: "Waiting for customer",
      order_id: null,
      created_at: null,
      updated_at: null,
      messages: [],
    };

    assert.deepEqual(mapSupportTicketFromResponse({ success: true, data: ticket }), ticket);
    assert.equal(mapSupportTicketFromResponse({ success: true }), null);
  });

  it("normalizes create ticket payloads", () => {
    assert.deepEqual(
      normalizeCreateSupportTicketInput({
        subject: "  Delayed delivery  ",
        category: " delivery_issue ",
        message: "  Where is my order?  ",
      }),
      {
        subject: "Delayed delivery",
        category: "delivery_issue",
        message: "Where is my order?",
        order_id: null,
        priority: undefined,
      },
    );
  });

  it("formats API errors from message or validation errors", () => {
    assert.equal(
      formatSupportApiError({ message: " Ticket not found " }, "Fallback"),
      "Ticket not found",
    );
    assert.equal(
      formatSupportApiError({ errors: { subject: ["Subject is required."] } }, "Fallback"),
      "Subject is required.",
    );
    assert.equal(formatSupportApiError({}, "Fallback"), "Fallback");
  });

  it("detects closed support tickets", () => {
    assert.equal(isSupportTicketClosed("closed"), true);
    assert.equal(isSupportTicketClosed("resolved"), true);
    assert.equal(isSupportTicketClosed("open"), false);
  });

  it("exposes support categories for the create ticket form", () => {
    assert.ok(CUSTOMER_SUPPORT_CATEGORIES.some((entry) => entry.value === "general"));
    assert.ok(CUSTOMER_SUPPORT_CATEGORIES.some((entry) => entry.value === "order_issue"));
  });
});
