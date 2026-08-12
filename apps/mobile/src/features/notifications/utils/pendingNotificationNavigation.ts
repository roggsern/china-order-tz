/**
 * One-time consumption of notification tap destinations (cold start + listeners).
 */

const consumedResponseIds = new Set<string>();
let pendingHref: string | null = null;

export function markNotificationResponseConsumed(responseId: string): boolean {
  const id = responseId.trim();
  if (!id) return false;
  if (consumedResponseIds.has(id)) {
    return false;
  }
  consumedResponseIds.add(id);
  // Bound memory for long-lived sessions.
  if (consumedResponseIds.size > 100) {
    const first = consumedResponseIds.values().next().value;
    if (typeof first === 'string') {
      consumedResponseIds.delete(first);
    }
  }
  return true;
}

export function wasNotificationResponseConsumed(responseId: string): boolean {
  return consumedResponseIds.has(responseId.trim());
}

export function queuePendingNotificationHref(href: string): void {
  pendingHref = href;
}

export function consumePendingNotificationHref(): string | null {
  const href = pendingHref;
  pendingHref = null;
  return href;
}

export function peekPendingNotificationHref(): string | null {
  return pendingHref;
}

/** Test helper */
export function resetPendingNotificationNavigationForTests(): void {
  consumedResponseIds.clear();
  pendingHref = null;
}
