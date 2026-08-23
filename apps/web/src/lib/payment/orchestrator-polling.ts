export const ORCHESTRATOR_POLL_INTERVAL_MS = 4000;

export const ORCHESTRATOR_WAITING_STATUSES = new Set(["pending", "processing"]);

export const ORCHESTRATOR_TERMINAL_STATUSES = new Set(["successful", "failed", "cancelled"]);

export function isOrchestratorWaitingStatus(status: string | null | undefined): boolean {
  return Boolean(status && ORCHESTRATOR_WAITING_STATUSES.has(status));
}

export function isOrchestratorTerminalStatus(status: string | null | undefined): boolean {
  return Boolean(status && ORCHESTRATOR_TERMINAL_STATUSES.has(status));
}
