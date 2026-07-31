import type { Metadata } from "next";
import {
  buildProductDetailMetadata,
  ProductDetailPageContent,
} from "@/lib/catalog/product-detail-page-content";

interface ProductDetailPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ProductDetailPageProps): Promise<Metadata> {
  const { slug } = await params;
  return buildProductDetailMetadata(slug);
}

export default async function ProductDetailPage({ params }: ProductDetailPageProps) {
  const { slug } = await params;
  return <ProductDetailPageContent slug={slug} />;
}
