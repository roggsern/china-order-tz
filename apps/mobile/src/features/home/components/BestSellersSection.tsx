import type { HomepageProductCard } from '../models/types';
import { ProductRailSection } from './ProductRailSection';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  products: HomepageProductCard[];
};

export function BestSellersSection({ title, subtitle, products }: Props) {
  return (
    <ProductRailSection
      title={title || 'Best sellers'}
      subtitle={subtitle}
      products={products}
    />
  );
}
