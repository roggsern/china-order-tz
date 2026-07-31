import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  buildProductDetailMetadata,
  ProductDetailPageContent,
} from "@/lib/catalog/product-detail-page-content";

interface ProductDetailByQueryPageProps {
  searchParams: Promise<{ slug?: string }>;
}

export async function generateMetadata({
  searchParams,
}: ProductDetailByQueryPageProps): Promise<Metadata> {
  const { slug = "" } = await searchParams;
  return buildProductDetailMetadata(slug);
}

/** Static product detail route — used directly and via /products/{slug} middleware rewrite. */
export default async function ProductDetailByQueryPage({
  searchParams,
}: ProductDetailByQueryPageProps) {
  const { slug = "" } = await searchParams;

  if (!slug.trim()) {
    notFound();
  }

  return <ProductDetailPageContent slug={slug} />;
}
