import { useCatalogUiStore } from './catalogUiStore';

describe('useCatalogUiStore', () => {
  beforeEach(() => {
    useCatalogUiStore.setState({
      selectedTzStoreSlug: null,
      selectedChinaCategorySlug: null,
      selectedTzCategorySlug: null,
    });
  });

  it('stores explicit TZ store selection', () => {
    useCatalogUiStore.getState().setSelectedTzStoreSlug('zion-mode');
    expect(useCatalogUiStore.getState().selectedTzStoreSlug).toBe('zion-mode');
  });

  it('can clear invalid/deselected store', () => {
    useCatalogUiStore.getState().setSelectedTzStoreSlug('gone');
    useCatalogUiStore.getState().setSelectedTzStoreSlug(null);
    expect(useCatalogUiStore.getState().selectedTzStoreSlug).toBeNull();
  });

  it('stores China category deep-link selection', () => {
    useCatalogUiStore.getState().setSelectedChinaCategorySlug('phones');
    expect(useCatalogUiStore.getState().selectedChinaCategorySlug).toBe('phones');
    useCatalogUiStore.getState().setSelectedChinaCategorySlug(null);
    expect(useCatalogUiStore.getState().selectedChinaCategorySlug).toBeNull();
  });

  it('keeps homepage category browse slugs unchanged (Automotive identity)', () => {
    useCatalogUiStore.getState().setSelectedChinaCategorySlug('automotive');
    expect(useCatalogUiStore.getState().selectedChinaCategorySlug).toBe(
      'automotive',
    );
  });

  it('stores TZ category deep-link selection independently of store', () => {
    useCatalogUiStore.getState().setSelectedTzStoreSlug('zion-mode');
    useCatalogUiStore.getState().setSelectedTzCategorySlug('dresses');
    expect(useCatalogUiStore.getState().selectedTzCategorySlug).toBe('dresses');
    useCatalogUiStore.getState().setSelectedTzCategorySlug(null);
    expect(useCatalogUiStore.getState().selectedTzStoreSlug).toBe('zion-mode');
    expect(useCatalogUiStore.getState().selectedTzCategorySlug).toBeNull();
  });
});
