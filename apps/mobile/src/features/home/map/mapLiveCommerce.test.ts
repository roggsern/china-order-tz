import { mapCatalogCategoryToHomepageCard } from './mapLiveCommerce';

describe('mapCatalogCategoryToHomepageCard', () => {
  it('forwards a catalog image URL onto the homepage card', () => {
    expect(
      mapCatalogCategoryToHomepageCard({
        id: '1',
        name: 'Automotive',
        slug: 'automotive',
        imageUrl: 'https://cdn.example/custom-automotive.jpg',
      }).imageUrl,
    ).toBe('https://cdn.example/custom-automotive.jpg');
  });

  it('does not invent a category image when catalog has none', () => {
    expect(
      mapCatalogCategoryToHomepageCard({
        id: '1',
        name: 'Automotive',
        slug: 'automotive',
      }).imageUrl,
    ).toBeNull();
  });
});
