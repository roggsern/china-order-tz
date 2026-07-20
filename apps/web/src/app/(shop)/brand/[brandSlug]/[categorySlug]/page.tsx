import { redirect } from "next/navigation";

interface BrandCategoryPageProps {
  params: Promise<{
    brandSlug: string;
    categorySlug: string;
  }>;
}

/** Legacy brand URLs → BUY FROM TZ store routes. */
export default async function BrandCategoryRedirectPage({ params }: BrandCategoryPageProps) {
  const { brandSlug, categorySlug } = await params;
  const storeSlug = brandSlug === "rovi-beauty-store" ? "rovi-beauty" : brandSlug;
  redirect(`/buy-from-tz/${storeSlug}/category/${categorySlug}`);
}
