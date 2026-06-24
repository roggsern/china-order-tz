"use client";

import { createContext, useContext, useState, type ReactNode } from "react";
import type { Product, ProductFormData } from "@/lib/types/catalog";
import { products as initialProducts } from "@/lib/catalog/products";

type AdminProductsContextValue = {
  products: Product[];
  addProduct: (data: ProductFormData) => void;
  updateProduct: (id: number, data: ProductFormData) => void;
  deleteProduct: (id: number) => void;
  getProduct: (id: number) => Product | undefined;
};

const AdminProductsContext = createContext<AdminProductsContextValue | null>(null);

function resolvePricing(data: ProductFormData): { price: number; oldPrice: number } {
  const hasSale = data.salePrice > 0 && data.salePrice < data.price;
  if (hasSale) {
    return { price: data.salePrice, oldPrice: data.price };
  }
  return { price: data.price, oldPrice: 0 };
}

function formDataToProduct(data: ProductFormData, id: number): Product {
  const { price, oldPrice } = resolvePricing(data);
  const images =
    data.images.length > 0
      ? data.images
      : [
          {
            id: 1,
            emoji: data.emoji,
            gradient: data.gradient,
            alt: data.name,
          },
        ];

  return {
    id,
    slug: data.slug,
    name: data.name,
    description: data.description,
    price,
    oldPrice,
    rating: data.rating,
    reviews: data.reviews,
    badge: data.badge,
    gradient: data.gradient,
    emoji: data.emoji,
    categorySlug: data.categorySlug,
    stock: data.stock,
    images,
    features: data.features
      .split("\n")
      .map((f) => f.trim())
      .filter(Boolean),
    featured: data.featured,
    status: data.status,
  };
}

export function AdminProductsProvider({ children }: { children: ReactNode }) {
  const [products, setProducts] = useState<Product[]>(initialProducts);

  const addProduct = (data: ProductFormData) => {
    const nextId = Math.max(0, ...products.map((p) => p.id)) + 1;
    setProducts((prev) => [...prev, formDataToProduct(data, nextId)]);
  };

  const updateProduct = (id: number, data: ProductFormData) => {
    setProducts((prev) =>
      prev.map((p) => (p.id === id ? formDataToProduct(data, id) : p)),
    );
  };

  const deleteProduct = (id: number) => {
    setProducts((prev) => prev.filter((p) => p.id !== id));
  };

  const getProduct = (id: number) => products.find((p) => p.id === id);

  return (
    <AdminProductsContext.Provider
      value={{ products, addProduct, updateProduct, deleteProduct, getProduct }}
    >
      {children}
    </AdminProductsContext.Provider>
  );
}

export function useAdminProducts() {
  const ctx = useContext(AdminProductsContext);
  if (!ctx) {
    throw new Error("useAdminProducts must be used within AdminProductsProvider");
  }
  return ctx;
}

export function productToFormData(product: Product): ProductFormData {
  const hasSale = product.oldPrice > product.price;
  return {
    name: product.name,
    slug: product.slug,
    description: product.description,
    price: hasSale ? product.oldPrice : product.price,
    salePrice: hasSale ? product.price : 0,
    rating: product.rating,
    reviews: product.reviews,
    badge: product.badge,
    gradient: product.gradient,
    emoji: product.emoji,
    categorySlug: product.categorySlug,
    stock: product.stock,
    features: product.features.join("\n"),
    featured: product.featured,
    status: product.status,
    images: product.images,
  };
}
