import {
  CatalogApiError,
  getFeaturedProducts as fetchFeaturedProducts,
  getProduct as fetchApiProduct,
  getProducts as fetchApiProducts,
} from "@/lib/api/products";
import {
  mapApiProductCardToCatalogProduct,
  mapApiProductDetailToCatalogProduct,
} from "@/lib/catalog/map-api-product";
import type { Product, ProductFilterOptions, ProductOrigin, SortOption } from "@/lib/types/catalog";
import { smartSearchProducts } from "@/lib/search/search-engine";

export { CatalogApiError } from "@/lib/api/products";

export type CatalogProductsResult = {
  products: Product[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function getProductsPage(options?: {
  page?: number;
  per_page?: number;
  featured?: boolean;
  category?: string;
  brand?: string;
  store?: string;
  origin?: ProductOrigin;
  search?: string;
}): Promise<CatalogProductsResult> {
  const result = await fetchApiProducts(options);

  return {
    products: result.products.map(mapApiProductCardToCatalogProduct),
    meta: result.meta,
  };
}

export async function getProducts(): Promise<Product[]> {
  const result = await getProductsPage({ per_page: 48, page: 1 });
  return result.products;
}

export async function getProductBrands(): Promise<string[]> {
  const { getBrands } = await import("@/lib/api/products");
  const brands = await getBrands();
  return brands.map((brand) => brand.name);
}

export async function getProductsByBrand(brand: string): Promise<Product[]> {
  const result = await getProductsPage({ brand, per_page: 48 });
  return result.products;
}

export async function getProductsByOrigin(origin: Product["origin"]): Promise<Product[]> {
  const products = await getProducts();
  return products.filter((product) => product.origin === origin);
}

export async function getPriceRange(items?: Product[]): Promise<{ min: number; max: number }> {
  const catalog = items ?? (await getProducts());
  if (catalog.length === 0) return { min: 0, max: 0 };
  const prices = catalog.map((product) => product.price);
  return { min: Math.min(...prices), max: Math.max(...prices) };
}

export async function getProductBySlug(slug: string): Promise<Product | undefined> {
  try {
    const product = await fetchApiProduct(slug);
    return mapApiProductDetailToCatalogProduct(product);
  } catch (error) {
    if (error instanceof CatalogApiError && error.statusCode === 404) {
      return undefined;
    }

    throw error;
  }
}

export async function getProductById(id: number): Promise<Product | undefined> {
  const products = await getProducts();
  return products.find((product) => product.id === id);
}

export async function getProductsByCategory(categorySlug: string): Promise<Product[]> {
  const result = await getProductsPage({ category: categorySlug, per_page: 48 });
  return result.products;
}

export async function getRelatedProducts(product: Product, limit = 4): Promise<Product[]> {
  if (!product.categorySlug) {
    return [];
  }

  const result = await getProductsPage({
    category: product.categorySlug,
    per_page: limit + 1,
  });

  return result.products
    .filter((entry) => entry.slug !== product.slug)
    .slice(0, limit);
}

export async function getFeaturedProducts(limit = 8): Promise<Product[]> {
  const featuredResult = await fetchFeaturedProducts(limit);

  if (featuredResult.length > 0) {
    return featuredResult.map(mapApiProductCardToCatalogProduct);
  }

  const fallbackResult = await fetchApiProducts({
    per_page: Math.max(limit * 2, 16),
    page: 1,
  });

  const fallbackCards = [...fallbackResult.products].sort((left, right) => {
    if (left.is_featured !== right.is_featured) {
      return left.is_featured ? -1 : 1;
    }

    return (right.average_rating ?? 0) - (left.average_rating ?? 0);
  });

  return fallbackCards.slice(0, limit).map(mapApiProductCardToCatalogProduct);
}

export async function getNewArrivalProducts(limit = 8): Promise<Product[]> {
  const result = await getProductsPage({
    per_page: Math.max(limit * 2, 16),
    page: 1,
  });

  return sortProducts(result.products, "newest").slice(0, limit);
}

export async function searchProducts(
  query: string,
  options?: { origin?: ProductOrigin },
): Promise<Product[]> {
  const result = await getProductsPage({
    search: query,
    per_page: 48,
  });

  return smartSearchProducts(result.products, query, {
    origin: options?.origin,
    activeOnly: true,
  });
}

export function sortProducts(items: Product[], sort: SortOption): Product[] {
  const sorted = [...items];
  switch (sort) {
    case "price-asc":
      return sorted.sort((a, b) => a.price - b.price);
    case "price-desc":
      return sorted.sort((a, b) => b.price - a.price);
    case "rating":
      return sorted.sort((a, b) => b.rating - a.rating);
    case "newest":
      return sorted.sort((a, b) => b.id - a.id);
    default:
      return sorted;
  }
}

export function filterProducts(items: Product[], options: ProductFilterOptions): Product[] {
  return items.filter((product) => {
    if (options.category && product.categorySlug !== options.category) return false;
    if (options.minPrice !== undefined && product.price < options.minPrice) return false;
    if (options.maxPrice !== undefined && product.price > options.maxPrice) return false;
    if (options.inStock && product.stock <= 0) return false;
    if (options.origin && product.origin !== options.origin) return false;
    if (options.brand && product.brand !== options.brand && product.brandSlug !== options.brand) return false;
    if (options.minRating !== undefined && product.rating < options.minRating) return false;
    return true;
  });
}
