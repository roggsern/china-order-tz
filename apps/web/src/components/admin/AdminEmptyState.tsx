"use client";

import type { ReactNode } from "react";

type AdminEmptyStateProps = {
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
};

/** Professional empty-state block for admin lists, tables, and dashboard sections. */
export function AdminEmptyState({
  title,
  description,
  action,
  className = "",
}: AdminEmptyStateProps) {
  return (
    <div className={`admin-empty-state ${className}`.trim()}>
      <p className="admin-empty-state-title">{title}</p>
      {description ? <p className="admin-empty-state-description">{description}</p> : null}
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  );
}
