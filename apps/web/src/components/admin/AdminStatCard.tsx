"use client";

import Link from "next/link";
import type { ReactNode } from "react";

type AdminStatCardProps = {
  label: string;
  value: number | string;
  sub?: string;
  icon?: ReactNode;
  href?: string;
  isText?: boolean;
  accent?: string;
  variant?: "default" | "gold" | "dark";
};

export function AdminStatCard({
  label,
  value,
  sub,
  icon,
  href,
  isText,
  accent,
  variant = "default",
}: AdminStatCardProps) {
  const variantClasses = {
    default: "admin-stat-card",
    gold: "admin-stat-card admin-stat-card-gold",
    dark: "admin-stat-card admin-stat-card-dark",
  };

  const content = (
    <div className={`${variantClasses[variant]} p-5 transition hover:shadow-md`}>
      <div className="flex items-start justify-between gap-3">
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">{label}</p>
        {icon}
      </div>
      <p
        className={`mt-2 font-bold ${accent ?? "text-zinc-900"} ${isText ? "text-lg sm:text-xl" : "text-2xl sm:text-3xl"}`}
      >
        {value}
      </p>
      {sub ? <p className="mt-1.5 text-xs text-zinc-500">{sub}</p> : null}
    </div>
  );

  if (href) {
    return (
      <Link href={href} className="block">
        {content}
      </Link>
    );
  }

  return content;
}
