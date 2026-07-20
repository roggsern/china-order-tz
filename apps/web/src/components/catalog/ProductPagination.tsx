import Link from "next/link";

interface ProductPaginationProps {
  currentPage: number;
  lastPage: number;
  basePath: string;
  searchParams: Record<string, string | undefined>;
}

function buildPageHref(
  basePath: string,
  searchParams: Record<string, string | undefined>,
  page: number,
): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(searchParams)) {
    if (value) {
      params.set(key, value);
    }
  }

  if (page > 1) {
    params.set("page", String(page));
  } else {
    params.delete("page");
  }

  const query = params.toString();
  return query ? `${basePath}?${query}` : basePath;
}

export function ProductPagination({
  currentPage,
  lastPage,
  basePath,
  searchParams,
}: ProductPaginationProps) {
  if (lastPage <= 1) {
    return null;
  }

  const pages = Array.from({ length: lastPage }, (_, index) => index + 1).filter((page) => {
    if (lastPage <= 7) {
      return true;
    }

    return page === 1 || page === lastPage || Math.abs(page - currentPage) <= 1;
  });

  return (
    <nav
      className="mt-10 flex flex-wrap items-center justify-center gap-2"
      aria-label="Product pagination"
    >
      {currentPage > 1 ? (
        <Link
          href={buildPageHref(basePath, searchParams, currentPage - 1)}
          className="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:border-[#c9a227]/40 hover:text-[#8b6914]"
        >
          Previous
        </Link>
      ) : null}

      {pages.map((page, index) => {
        const previousPage = pages[index - 1];
        const showEllipsis = previousPage !== undefined && page - previousPage > 1;

        return (
          <span key={page} className="flex items-center gap-2">
            {showEllipsis ? <span className="px-1 text-zinc-400">…</span> : null}
            <Link
              href={buildPageHref(basePath, searchParams, page)}
              aria-current={page === currentPage ? "page" : undefined}
              className={`inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold transition ${
                page === currentPage
                  ? "bg-zinc-900 text-white"
                  : "border border-zinc-200 bg-white text-zinc-700 hover:border-[#c9a227]/40 hover:text-[#8b6914]"
              }`}
            >
              {page}
            </Link>
          </span>
        );
      })}

      {currentPage < lastPage ? (
        <Link
          href={buildPageHref(basePath, searchParams, currentPage + 1)}
          className="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:border-[#c9a227]/40 hover:text-[#8b6914]"
        >
          Next
        </Link>
      ) : null}
    </nav>
  );
}
