import type { Product, ProductFilterOptions, SortOption } from "@/lib/types/catalog";
import { getActiveServerProducts } from "@/lib/services/product-service.server";
import { SEED_PRODUCTS } from "@/lib/catalog/seed-products";

export { SEED_PRODUCTS };

export async function getProducts(): Promise<Product[]> {
  return getActiveServerProducts();
}

export async function getProductBrands(): Promise<string[]> {
  const products = await getProducts();
  return [...new Set(products.map((product) => product.brand).filter(Boolean))] as string[];
}

export async function getProductsByBrand(brand: string): Promise<Product[]> {
  const products = await getProducts();
  return products.filter((product) => product.brand === brand);
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
  const products = await getProducts();
  return products.find((product) => product.slug === slug);
}

export async function getProductById(id: number): Promise<Product | undefined> {
  const products = await getProducts();
  return products.find((product) => product.id === id);
}

export async function getProductsByCategory(categorySlug: string): Promise<Product[]> {
  const products = await getProducts();
  return products.filter((product) => product.categorySlug === categorySlug);
}

export async function getRelatedProducts(product: Product, limit = 4): Promise<Product[]> {
  const products = await getProducts();
  return products
    .filter(
      (entry) =>
        entry.categorySlug === product.categorySlug &&
        entry.id !== product.id &&
        entry.status === "active",
    )
    .slice(0, limit);
}

export async function getFeaturedProducts(limit = 8): Promise<Product[]> {
  const products = await getProducts();
  return products.filter((product) => product.featured && product.status === "active").slice(0, limit);
}

export async function searchProducts(query: string): Promise<Product[]> {
  const products = await getProducts();
  const q = query.toLowerCase().trim();
  if (!q) return products;
  return products.filter(
    (product) =>
      product.name.toLowerCase().includes(q) ||
      product.description.toLowerCase().includes(q) ||
      product.categorySlug.includes(q),
  );
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
    if (options.brand && product.brand !== options.brand) return false;
    if (options.minRating !== undefined && product.rating < options.minRating) return false;
    return true;
  });
}
