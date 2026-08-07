"use client";

export type AdminProductSectionTab = {
  id: string;
  label: string;
  shortLabel?: string;
};

type AdminProductSectionTabsProps = {
  eyebrow: string;
  title: string;
  tabs: AdminProductSectionTab[];
  activeTabId: string;
  onSelectTab: (tabId: string) => void;
};

/**
 * Shared product workspace section navigation.
 * Matches the Add Product wizard chrome so Edit feels like the same system.
 */
export function AdminProductSectionTabs({
  eyebrow,
  title,
  tabs,
  activeTabId,
  onSelectTab,
}: AdminProductSectionTabsProps) {
  const current = tabs.find((tab) => tab.id === activeTabId) ?? tabs[0];

  return (
    <div className="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#8b6914]">
            {eyebrow}
          </p>
          <p className="mt-1 truncate text-sm font-semibold text-zinc-900">{title}</p>
          {current ? (
            <p className="mt-1 text-xs text-zinc-500">
              Section: {current.label}
            </p>
          ) : null}
        </div>
      </div>

      <nav
        className="mt-4 -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 sm:grid sm:grid-cols-3 sm:overflow-visible lg:grid-cols-4 xl:flex xl:flex-wrap"
        aria-label="Product sections"
      >
        {tabs.map((tab) => {
          const isCurrent = tab.id === activeTabId;
          return (
            <button
              key={tab.id}
              type="button"
              aria-current={isCurrent ? "page" : undefined}
              onClick={() => onSelectTab(tab.id)}
              className={`min-w-[7.25rem] shrink-0 rounded-lg border px-2.5 py-2 text-left transition sm:min-w-0 xl:min-w-[7.5rem] xl:flex-1 ${
                isCurrent
                  ? "border-[#c9a227] bg-[#fff8e7] text-zinc-900 shadow-sm"
                  : "border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50"
              }`}
            >
              <span className="block truncate text-[11px] font-semibold sm:hidden">
                {tab.shortLabel ?? tab.label}
              </span>
              <span className="hidden truncate text-[11px] font-semibold sm:block">
                {tab.label}
              </span>
            </button>
          );
        })}
      </nav>
    </div>
  );
}
