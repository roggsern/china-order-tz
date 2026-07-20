import { redirect, notFound } from "next/navigation";
import { getTzStoreProduct } from "@/lib/api/tz-stores";

interface ProductPageProps {
  params: Promise<{ storeSlug: string; productSlug: string }>;
}

/**
 * Store-scoped product URL resolves ownership then reuses the main PDP.
 */
export default async function BuyFromTzProductPage({ params }: ProductPageProps) {
  const { storeSlug, productSlug } = await params;

  try {
    await getTzStoreProduct(storeSlug, productSlug);
  } catch {
    notFound();
  }

  redirect(`/products/${productSlug}`);
}
