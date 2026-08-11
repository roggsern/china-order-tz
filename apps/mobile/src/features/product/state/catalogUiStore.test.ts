import { useCatalogUiStore } from './catalogUiStore';

describe('useCatalogUiStore', () => {
  beforeEach(() => {
    useCatalogUiStore.setState({ selectedTzStoreSlug: null });
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
});
