import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it } from "node:test";
import {
  ERROR_HOME_HREF,
  ERROR_HOME_LABEL,
  ERROR_RETRY_LABEL,
  GLOBAL_ERROR_MESSAGE,
  GLOBAL_ERROR_TITLE,
  ROUTE_ERROR_MESSAGE,
  ROUTE_ERROR_TITLE,
} from "@/lib/ui/error-recovery";

describe("error recovery copy", () => {
  it("uses safe user-facing route error messaging", () => {
    assert.match(ROUTE_ERROR_TITLE, /something went wrong/i);
    assert.match(ROUTE_ERROR_MESSAGE, /try again/i);
    assert.doesNotMatch(ROUTE_ERROR_MESSAGE, /stack|trace|env/i);
  });

  it("uses safe global error messaging without internals", () => {
    assert.match(GLOBAL_ERROR_TITLE, /unexpected error/i);
    assert.match(GLOBAL_ERROR_MESSAGE, /refresh|homepage/i);
    assert.doesNotMatch(GLOBAL_ERROR_MESSAGE, /stack|trace|api|env/i);
  });

  it("defines retry and home recovery actions", () => {
    assert.equal(ERROR_RETRY_LABEL, "Try again");
    assert.equal(ERROR_HOME_LABEL, "Go to homepage");
    assert.equal(ERROR_HOME_HREF, "/");
  });
});

describe("error boundary UI files", () => {
  it("route error page exposes retry and home navigation", () => {
    const source = readFileSync("src/app/error.tsx", "utf8");
    assert.match(source, /reset\(\)/);
    assert.match(source, /ERROR_RETRY_LABEL/);
    assert.match(source, /ERROR_HOME_HREF/);
    assert.doesNotMatch(source, /\{error\.message\}|\{error\.stack\}/);
  });

  it("global error page exposes retry and home navigation", () => {
    const source = readFileSync("src/app/global-error.tsx", "utf8");
    assert.match(source, /reset\(\)/);
    assert.match(source, /GLOBAL_ERROR_MESSAGE/);
    assert.match(source, /<html/);
    assert.match(source, /<body/);
    assert.doesNotMatch(source, /\{error\.message\}|\{error\.stack\}/);
  });
});

describe("auth login BFF route logging", () => {
  it("does not log upstream response bodies or credentials", () => {
    const source = readFileSync("src/app/api/auth/login/route.ts", "utf8");

    assert.doesNotMatch(source, /console\.log/);
    assert.doesNotMatch(source, /console\.info/);
    assert.doesNotMatch(source, /console\.debug/);
    assert.doesNotMatch(source, /console\.(log|warn|info|debug)\([^\)]*BODY/i);
    assert.doesNotMatch(source, /API_URL_USED/);
    assert.match(source, /console\.warn\("auth\.login\.upstream_failed"/);
    assert.doesNotMatch(source, /console\.warn\([^\)]*text/);
  });
});
