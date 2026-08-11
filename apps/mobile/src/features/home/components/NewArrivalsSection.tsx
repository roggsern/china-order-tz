import type { HomepageProductCard } from '../models/types';
import { ProductRailSection } from './ProductRailSection';

type Props = {
  title?: string | null;
  subtitle?: string | null;
  products: HomepageProductCard[];
};

export function NewArrivalsSection({ title, subtitle, products }: Props) {
  return (
    <ProductRailSection
      title={title || 'New arrivals'}
      subtitle={subtitle}
      products={products}
    />
  );
}
