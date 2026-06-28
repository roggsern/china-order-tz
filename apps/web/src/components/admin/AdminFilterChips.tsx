"use client";

export type AdminFilterChipOption = {
  id: string;
  label: string;
  count?: number;
};

interface AdminFilterChipsProps {
  chips: AdminFilterChipOption[];
  activeId: string;
  onChange: (id: string) => void;
  ariaLabel: string;
  className?: string;
}

export function AdminFilterChips({
  chips,
  activeId,
  onChange,
  ariaLabel,
  className = "",
}: AdminFilterChipsProps) {
  return (
    <div
      className={`flex gap-2 overflow-x-auto pb-1 ${className}`}
      role="tablist"
      aria-label={ariaLabel}
    >
      {chips.map((chip) => {
        const isActive = activeId === chip.id;

        return (
          <button
            key={chip.id}
            type="button"
            role="tab"
            aria-selected={isActive}
            onClick={() => onChange(chip.id)}
            className={`inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition-all duration-200 ${
              isActive
                ? "bg-[#c9a227] text-zinc-900 shadow-sm ring-1 ring-[#c9a227]/50"
                : "bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200/80 hover:bg-zinc-100 hover:text-zinc-900"
            }`}
          >
            {chip.label}
            {chip.count !== undefined && (
              <span
                className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                  isActive ? "bg-zinc-900/10 text-zinc-900" : "bg-zinc-200 text-zinc-700"
                }`}
              >
                {chip.count}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
