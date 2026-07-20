import type { HTMLAttributes } from "react";

interface SkeletonProps extends HTMLAttributes<HTMLDivElement> {
  className?: string;
  rounded?: "none" | "sm" | "md" | "lg" | "xl" | "2xl" | "3xl" | "full";
}

const ROUND: Record<NonNullable<SkeletonProps["rounded"]>, string> = {
  none: "rounded-none",
  sm: "rounded-sm",
  md: "rounded-md",
  lg: "rounded-lg",
  xl: "rounded-xl",
  "2xl": "rounded-2xl",
  "3xl": "rounded-3xl",
  full: "rounded-full",
};

/** Premium shimmer skeleton block. Prefer layout-matching shapes over spinners. */
export function Skeleton({ className = "", rounded = "xl", ...props }: SkeletonProps) {
  return (
    <div
      aria-hidden
      className={`skeleton-shimmer ${ROUND[rounded]} ${className}`}
      {...props}
    />
  );
}

export function SkeletonText({
  lines = 3,
  className = "",
}: {
  lines?: number;
  className?: string;
}) {
  return (
    <div className={`space-y-2.5 ${className}`} aria-hidden>
      {Array.from({ length: lines }).map((_, index) => (
        <Skeleton
          key={index}
          className={`h-3 ${index === lines - 1 ? "w-2/3" : "w-full"}`}
          rounded="md"
        />
      ))}
    </div>
  );
}
