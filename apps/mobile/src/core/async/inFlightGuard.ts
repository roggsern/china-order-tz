/** Client-side guard for non-idempotent actions already shown as pending. */
export function canSubmitInFlightAction(isPending: boolean): boolean {
  return !isPending;
}
