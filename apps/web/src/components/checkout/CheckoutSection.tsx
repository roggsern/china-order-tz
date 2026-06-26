import type { ReactNode } from "react";

interface CheckoutSectionProps {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
}

export function CheckoutSection({
  title,
  description,
  children,
  className = "",
}: CheckoutSectionProps) {
  return (
    <section
      className={`rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7 ${className}`}
    >
      <div className="mb-6 border-b border-zinc-100 pb-5">
        <h2 className="text-lg font-bold tracking-tight text-zinc-900 sm:text-xl">{title}</h2>
        {description && (
          <p className="mt-1.5 text-sm leading-relaxed text-zinc-500">{description}</p>
        )}
      </div>
      {children}
    </section>
  );
}
