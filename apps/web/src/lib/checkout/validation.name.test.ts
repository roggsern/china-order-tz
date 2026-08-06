import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { splitFullName, validateFullName } from "./validation";

describe("splitFullName", () => {
  it("keeps a single token as first name with empty last name", () => {
    assert.deepEqual(splitFullName("Robert"), {
      firstName: "Robert",
      lastName: "",
    });
  });

  it("splits a two-part display name without duplication", () => {
    assert.deepEqual(splitFullName("Robert Musa"), {
      firstName: "Robert",
      lastName: "Musa",
    });
  });

  it("keeps extra tokens in the last name", () => {
    assert.deepEqual(splitFullName("Mama Asha Juma"), {
      firstName: "Mama",
      lastName: "Asha Juma",
    });
  });
});

describe("validateFullName (recipient-style)", () => {
  it("allows a one-word recipient name of sufficient length", () => {
    assert.equal(validateFullName("Robert"), undefined);
  });
});
