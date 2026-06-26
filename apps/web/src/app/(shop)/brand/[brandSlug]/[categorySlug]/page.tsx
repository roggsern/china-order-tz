import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { getBrandBySlug, getBrandCategory } from "@/lib/catalog/brands";

interface BrandCategoryPageProps {
  params: Promise<{
    brandSlug: string;
    categorySlug: string;
  }>;
}

export async function generateMetadata({
  params,
}: BrandCategoryPageProps): Promise<Metadata> {
  const { brandSlug, categorySlug } = await params;
  const brand = getBrandBySlug(brandSlug);
  const category = getBrandCategory(brandSlug, categorySlug);

  if (!brand || !category) {
    return { title: "Not Found — CHINA ORDER TZ" };
  }

  const brandLabel = brand.name
    .split(" ")
    .map((word) => word.charAt(0) + word.slice(1).toLowerCase())
    .join(" ");

  return {
    title: `${category.name} — ${brandLabel} — CHINA ORDER TZ`,
    description: `Shop ${category.name} from ${brandLabel} at CHINA ORDER TZ.`,
  };
}

export default async function BrandCategoryPage({ params }: BrandCategoryPageProps) {
  const { brandSlug, categorySlug } = await params;
  const brand = getBrandBySlug(brandSlug);
  const category = getBrandCategory(brandSlug, categorySlug);

  if (!brand || !category) {
    notFound();
  }

  const brandLabel = brand.name
    .split(" ")
    .map((word) => word.charAt(0) + word.slice(1).toLowerCase())
    .join(" ");

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs
          items={[
            { label: "Buy From TZ", href: "/products" },
            { label: brandLabel, href: `/products?brand=${brand.slug}` },
            { label: category.name },
          ]}
        />

        <div className="mt-6">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            {brand.name}
          </p>
          <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            {category.name}
          </h1>
          <p className="mt-3 max-w-2xl text-base text-zinc-500">
            Browse {category.name.toLowerCase()} from {brandLabel}. Product listings coming soon.
          </p>
        </div>

        <div className="mt-10 rounded-2xl border border-zinc-200 bg-white p-8 text-center">
          <span className="text-4xl">{brand.icon}</span>
          <p className="mt-4 text-sm text-zinc-500">
            This collection page is ready for products. Check back soon or explore other categories.
          </p>
          <Link
            href={`/products?brand=${brand.slug}`}
            className="mt-6 inline-flex items-center rounded-full bg-zinc-900 px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
          >
            View all {brandLabel}
          </Link>
        </div>
      </div>
    </div>
  );
}
