"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";

type ButtonVariant = "primary" | "secondary" | "ghost" | "danger";
type ButtonSize = "sm" | "md" | "lg";

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
  primary:
    "bg-gradient-to-r from-[#c9a227] to-[#e8c547] text-zinc-900 shadow-lg shadow-[#c9a227]/25 hover:from-[#b8921f] hover:to-[#d4b83d] hover:shadow-[#c9a227]/35 disabled:from-zinc-300 disabled:to-zinc-300 disabled:text-zinc-600 disabled:shadow-none",
  secondary:
    "border border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 disabled:border-zinc-100 disabled:text-zinc-400",
  ghost: "text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 disabled:text-zinc-400",
  danger:
    "border border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100 disabled:opacity-50",
};

const SIZE_CLASSES: Record<ButtonSize, string> = {
  sm: "px-3 py-1.5 text-xs font-semibold rounded-lg",
  md: "px-4 py-3 text-sm font-semibold rounded-xl",
  lg: "px-4 py-4 text-sm font-bold rounded-xl",
};

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  fullWidth?: boolean;
  children: ReactNode;
}

export function Button({
  variant = "primary",
  size = "md",
  fullWidth = false,
  className = "",
  type = "button",
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      className={`inline-flex items-center justify-center gap-2 transition duration-200 disabled:cursor-not-allowed ${VARIANT_CLASSES[variant]} ${SIZE_CLASSES[size]} ${fullWidth ? "w-full" : ""} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
