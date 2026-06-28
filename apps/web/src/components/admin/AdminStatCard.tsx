"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type ReactNode } from "react";

type AdminStatCardProps = {
  label: string;
  value: number | string;
  sub?: string;
  icon?: ReactNode;
  href?: string;
  isText?: boolean;
  accent?: string;
  variant?: "default" | "gold" | "dark";
  livePulse?: boolean;
};

function AnimatedStatValue({
  value,
  className,
  livePulse,
}: {
  value: number | string;
  className: string;
  livePulse?: boolean;
}) {
  const prevRef = useRef(value);
  const [pulse, setPulse] = useState(false);

  useEffect(() => {
    if (!livePulse || prevRef.current === value) {
      prevRef.current = value;
      return;
    }

    prevRef.current = value;
    setPulse(true);
    const timer = setTimeout(() => setPulse(false), 600);
    return () => clearTimeout(timer);
  }, [value, livePulse]);

  return <span className={`${className} ${pulse ? "admin-stat-pulse" : ""}`}>{value}</span>;
}

export function AdminStatCard({
  label,
  value,
  sub,
  icon,
  href,
  isText,
  accent,
  variant = "default",
  livePulse = false,
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
      <AnimatedStatValue
        value={value}
        livePulse={livePulse}
        className={`mt-2 block font-bold ${accent ?? "text-zinc-900"} ${isText ? "text-lg sm:text-xl" : "text-2xl sm:text-3xl"}`}
      />
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
