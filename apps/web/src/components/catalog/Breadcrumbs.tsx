import Link from "next/link";
import { ChevronLeftIcon } from "@/components/home/icons";

export type BreadcrumbItem = {
  label: string;
  href?: string;
};

interface BreadcrumbsProps {
  items: BreadcrumbItem[];
}

export function Breadcrumbs({ items }: BreadcrumbsProps) {
  return (
    <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-sm">
      <Link
        href="/"
        className="flex items-center gap-1 text-zinc-500 transition hover:text-[#c9a227]"
      >
        <ChevronLeftIcon className="h-4 w-4" />
        Home
      </Link>
      {items.map((item, index) => (
        <span key={item.label} className="flex items-center gap-2">
          <span className="text-zinc-300">/</span>
          {item.href && index < items.length - 1 ? (
            <Link href={item.href} className="text-zinc-500 transition hover:text-[#c9a227]">
              {item.label}
            </Link>
          ) : (
            <span className="font-medium text-zinc-900">{item.label}</span>
          )}
        </span>
      ))}
    </nav>
  );
}
