import type { Metadata } from "next";
import {
  fetchUnifiedSearchProducts,
  MarketplaceSearchApiError,
} from "@/lib/api/marketplace-search";
import {
  resolveSearchPageHeading,
  resolveSearchPageScope,
  resolveSearchPageScopeLabel,
} from "@/lib/search/search-url";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductPagination } from "@/components/catalog/ProductPagination";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";

export const metadata: Metadata = {
  title: "Search — CHINA ORDER TZ",
  description: "Search products across China imports and Buy from Dar.",
};

interface SearchPageProps {
  searchParams: Promise<{
    q?: string;
    scope?: string;
    page?: string;
  }>;
}

const PER_PAGE = 24;

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const params = await searchParams;
  const query = params.q ?? "";
  const scope = resolveSearchPageScope(params.scope);
  const currentPage = Math.max(1, Number(params.page) || 1);
  const heading = resolveSearchPageHeading(query);
  const scopeLabel = resolveSearchPageScopeLabel(scope);

  try {
    const { products, meta } = await fetchUnifiedSearchProducts({
      q: query,
      scope,
      page: currentPage,
      perPage: PER_PAGE,
      sort: "relevance",
    });

    const paginationSearchParams = {
      q: query.trim() || undefined,
      scope,
    };

    return (
      <div className="bg-zinc-50 py-10 sm:py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Breadcrumbs items={[{ label: "Search" }]} />

          <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
                Marketplace search
              </p>
              <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                {heading}
              </h1>
              <p className="mt-3 max-w-2xl text-base text-zinc-500">
                Showing matches in {scopeLabel}.
              </p>
            </div>
          </div>

          <div className="mt-8 flex items-center justify-between">
            <p className="text-sm text-zinc-500">
              {meta.total} product{meta.total !== 1 ? "s" : ""}
            </p>
          </div>

          <div className="mt-8">
            <ProductGrid
              products={products}
              emptyTitle={
                query.trim() ? "No matching products found" : "Start a search"
              }
              emptyMessage={
                query.trim()
                  ? "Try different keywords or switch marketplace scope."
                  : "Enter a keyword in the header search to find products."
              }
              searchQuery={query.trim() || undefined}
            />
            <ProductPagination
              currentPage={meta.current_page}
              lastPage={meta.last_page}
              basePath="/search"
              searchParams={paginationSearchParams}
            />
          </div>
        </div>
      </div>
    );
  } catch (error) {
    const message =
      error instanceof MarketplaceSearchApiError
        ? error.message
        : "Something went wrong while loading search results.";

    return (
      <div className="bg-zinc-50 py-10 sm:py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Breadcrumbs items={[{ label: "Search" }]} />
          <div className="mt-8">
            <CatalogErrorState message={message} />
          </div>
        </div>
      </div>
    );
  }
}
