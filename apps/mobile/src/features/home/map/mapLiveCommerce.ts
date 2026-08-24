import type { CatalogCategory, CatalogProductCard, CatalogStore } from '@/src/features/product';
import type {
  HomepageCategoryCard,
  HomepageProductCard,
  HomepageStoreCard,
} from '../models/types';

export function mapCatalogProductToHomepageCard(
  product: CatalogProductCard,
): HomepageProductCard {
  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    price: product.price,
    compareAtPrice: product.compareAtPrice ?? null,
    imageUrl: product.imageUrl,
    commerceChannelCode: product.commerceChannelCode ?? null,
    storeSlug: product.storeSlug ?? null,
  };
}

export function mapCatalogCategoryToHomepageCard(
  category: CatalogCategory,
): HomepageCategoryCard {
  return {
    id: category.id,
    name: category.name,
    slug: category.slug,
    description: null,
    imageUrl: category.imageUrl ?? null,
  };
}

export function mapCatalogStoreToHomepageCard(
  store: CatalogStore,
): HomepageStoreCard {
  return {
    id: store.id,
    name: store.name,
    slug: store.slug,
    description: store.description ?? null,
    imageUrl: store.logoUrl ?? null,
  };
}
