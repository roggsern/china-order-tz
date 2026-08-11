import type { RenderableHomepageSection } from '../models/types';
import { BestSellersSection } from './BestSellersSection';
import { CampaignSection } from './CampaignSection';
import { FeaturedProductsSection } from './FeaturedProductsSection';
import { HeroSection } from './HeroSection';
import { NewArrivalsSection } from './NewArrivalsSection';

type Props = {
  sections: RenderableHomepageSection[];
};

/** Renders mapped CMS sections; skips unknown kinds. */
export function HomepageSections({ sections }: Props) {
  return (
    <>
      {sections.map((section) => {
        switch (section.kind) {
          case 'HERO':
            return (
              <HeroSection
                key={section.key}
                title={section.title}
                subtitle={section.subtitle}
                slides={section.slides}
              />
            );
          case 'CAMPAIGN':
            return <CampaignSection key={section.key} campaign={section.campaign} />;
          case 'FEATURED_PRODUCTS':
            return (
              <FeaturedProductsSection
                key={section.key}
                title={section.title}
                subtitle={section.subtitle}
                products={section.products}
              />
            );
          case 'NEW_ARRIVALS':
            return (
              <NewArrivalsSection
                key={section.key}
                title={section.title}
                subtitle={section.subtitle}
                products={section.products}
              />
            );
          case 'BEST_SELLERS':
            return (
              <BestSellersSection
                key={section.key}
                title={section.title}
                subtitle={section.subtitle}
                products={section.products}
              />
            );
          default:
            return null;
        }
      })}
    </>
  );
}
