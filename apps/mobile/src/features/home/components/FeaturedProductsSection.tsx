import type { HomepageProductCard } from '../models/types';
import { ProductRailSection } from './ProductRailSection';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  products: HomepageProductCard[];
};

export function FeaturedProductsSection({ title, subtitle, products }: Props) {
  return (
    <ProductRailSection
      title={title || 'Featured products'}
      subtitle={subtitle}
      products={products}
    />
  );
}
