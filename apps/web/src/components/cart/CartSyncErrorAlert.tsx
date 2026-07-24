"use client";

type CartSyncErrorAlertProps = {
  message: string;
  onDismiss?: () => void;
  className?: string;
};

export function CartSyncErrorAlert({
  message,
  onDismiss,
  className = "",
}: CartSyncErrorAlertProps) {
  if (!message.trim()) {
    return null;
  }

  return (
    <div
      className={`rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 ${className}`.trim()}
      role="alert"
      data-cart-sync-error="true"
    >
      <p>{message}</p>
      {onDismiss ? (
        <button
          type="button"
          onClick={onDismiss}
          className="mt-2 text-xs font-semibold text-amber-900 underline underline-offset-2"
        >
          Dismiss
        </button>
      ) : null}
    </div>
  );
}
