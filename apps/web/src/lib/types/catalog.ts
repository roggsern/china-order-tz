export type Category = {
  slug: string;
  name: string;
  description: string;
  gradient: string;
  icon: string;
};

export type ProductStatus = "active" | "hidden";

export type ProductImage = {
  id: number;
  emoji: string;
  gradient: string;
  alt: string;
  url?: string;
};

export type Product = {
  id: number;
  slug: string;
  name: string;
  description: string;
  price: number;
  oldPrice: number;
  rating: number;
  reviews: number;
  badge: string;
  gradient: string;
  emoji: string;
  categorySlug: string;
  stock: number;
  images: ProductImage[];
  features: string[];
  featured: boolean;
  status: ProductStatus;
};

export type ProductFormData = {
  name: string;
  slug: string;
  description: string;
  price: number;
  salePrice: number;
  rating: number;
  reviews: number;
  badge: string;
  gradient: string;
  emoji: string;
  categorySlug: string;
  stock: number;
  features: string;
  featured: boolean;
  status: ProductStatus;
  images: ProductImage[];
};

export type SortOption = "featured" | "price-asc" | "price-desc" | "rating" | "newest";
