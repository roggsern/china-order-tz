/**
 * Documents next-page retry contract for Search/Orders infinite lists:
 * keep loaded pages; retry calls fetchNextPage (failed page), not full reset.
 */
export function shouldShowNextPageRetry(params: {
  isFetchNextPageError: boolean;
  loadedItemCount: number;
}): boolean {
  return params.isFetchNextPageError && params.loadedItemCount > 0;
}

describe('pagination next-page retry', () => {
  it('exposes retry when next page fails and items remain', () => {
    expect(
      shouldShowNextPageRetry({
        isFetchNextPageError: true,
        loadedItemCount: 10,
      }),
    ).toBe(true);
  });

  it('does not show next-page retry when list is empty (use full-page error)', () => {
    expect(
      shouldShowNextPageRetry({
        isFetchNextPageError: true,
        loadedItemCount: 0,
      }),
    ).toBe(false);
  });

  it('hides retry when next page succeeds', () => {
    expect(
      shouldShowNextPageRetry({
        isFetchNextPageError: false,
        loadedItemCount: 10,
      }),
    ).toBe(false);
  });
});
