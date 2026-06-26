import type { ReactNode } from "react";

interface CheckoutFieldProps {
  id: string;
  label: string;
  error?: string;
  required?: boolean;
  children: ReactNode;
  className?: string;
  hint?: string;
}

export function CheckoutField({
  id,
  label,
  error,
  required = false,
  children,
  className = "",
  hint,
}: CheckoutFieldProps) {
  return (
    <div className={className}>
      <label htmlFor={id} className="block text-sm font-medium text-zinc-800">
        {label}
        {required && <span className="text-red-500"> *</span>}
      </label>
      {hint && <p className="mt-0.5 text-xs text-zinc-500">{hint}</p>}
      <div className="mt-2">{children}</div>
      {error && (
        <p id={`${id}-error`} className="mt-1.5 text-xs font-medium text-red-600" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

export const checkoutInputClass =
  "w-full rounded-xl border border-zinc-200 bg-zinc-50/60 px-4 py-3 text-sm text-zinc-900 placeholder:text-zinc-400 outline-none transition focus:border-[#c9a227] focus:bg-white focus:ring-2 focus:ring-[#c9a227]/20";

export const checkoutTextareaClass =
  "w-full resize-y rounded-xl border border-zinc-200 bg-zinc-50/60 px-4 py-3 text-sm text-zinc-900 placeholder:text-zinc-400 outline-none transition focus:border-[#c9a227] focus:bg-white focus:ring-2 focus:ring-[#c9a227]/20";

export function checkoutInputClassName(hasError?: boolean): string {
  if (!hasError) {
    return checkoutInputClass;
  }
  return `${checkoutInputClass} border-red-300 bg-red-50/40 focus:border-red-400 focus:ring-red-200`;
}
