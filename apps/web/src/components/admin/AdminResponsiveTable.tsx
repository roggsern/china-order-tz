import type { ReactNode } from "react";

interface AdminResponsiveTableProps {
  /** Standard table markup — shown from `md` breakpoint up. */
  children: ReactNode;
  /** Optional stacked card list — shown below `md` when provided. */
  mobileCards?: ReactNode;
  className?: string;
}

/**
 * Responsive table shell: horizontal scroll on tablet/desktop, optional card stack on mobile.
 * Existing pages can wrap current `<table>` without changing row markup.
 */
export function AdminResponsiveTable({
  children,
  mobileCards,
  className = "",
}: AdminResponsiveTableProps) {
  return (
    <div className={`admin-responsive-table ${className}`.trim()}>
      {mobileCards ? <div className="admin-table-cards md:hidden">{mobileCards}</div> : null}
      <div className={mobileCards ? "admin-table-scroll hidden md:block" : "admin-table-scroll"}>
        {children}
      </div>
    </div>
  );
}
