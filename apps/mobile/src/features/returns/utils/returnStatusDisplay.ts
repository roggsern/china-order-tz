/**
 * Return / refund presentation stays distinct from order and payment status.
 * Never maps a return request onto order refunded.
 */

const RETURN_STATUS_LABELS: Record<string, string> = {
  requested: 'Return requested',
  approved: 'Return approved',
  rejected: 'Return rejected',
  inspection: 'Return in inspection',
  completed: 'Return completed',
  cancelled: 'Return cancelled',
};

const REFUND_STATUS_LABELS: Record<string, string> = {
  pending: 'Refund pending',
  requested: 'Refund requested',
  approved: 'Refund approved',
  processing: 'Refund in progress',
  completed: 'Refund completed',
  failed: 'Refund failed',
  rejected: 'Refund rejected',
};

function normalize(value: string | null | undefined): string {
  return value?.trim().toLowerCase() ?? '';
}

export function resolveReturnDisplayStatus(status: string | null | undefined): {
  key: string;
  label: string;
} {
  const key = normalize(status);
  if (!key) return { key: 'unknown', label: 'Return status unavailable' };
  return {
    key,
    label: RETURN_STATUS_LABELS[key] ?? 'Return in review',
  };
}

export function resolveRefundDisplayStatus(status: string | null | undefined): {
  key: string;
  label: string;
} {
  const key = normalize(status);
  if (!key) return { key: 'none', label: 'No refund yet' };
  return {
    key,
    label: REFUND_STATUS_LABELS[key] ?? 'Refund update pending',
  };
}

export function returnDisplayTone(
  key: string,
): 'success' | 'error' | 'warning' | 'info' | 'neutral' {
  switch (normalize(key)) {
    case 'rejected':
    case 'cancelled':
    case 'failed':
      return 'error';
    case 'requested':
    case 'pending':
    case 'inspection':
    case 'processing':
      return 'warning';
    case 'approved':
    case 'completed':
      return 'success';
    default:
      return 'neutral';
  }
}
