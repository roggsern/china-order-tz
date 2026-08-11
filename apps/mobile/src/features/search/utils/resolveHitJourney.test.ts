import { resolveHitJourney } from '../utils/resolveHitJourney';
import type { SearchHit } from '../models/types';

function hit(partial: Partial<SearchHit>): SearchHit {
  return {
    id: '1',
    slug: 'item',
    name: 'Item',
    price: null,
    imageUrl: null,
    marketplace: null,
    ...partial,
  };
}

describe('resolveHitJourney', () => {
  it('maps china marketplace to CHINA_IMPORT', () => {
    expect(resolveHitJourney(hit({ marketplace: 'china' }))).toBe('CHINA_IMPORT');
  });

  it('maps tz marketplace to TZ_LOCAL', () => {
    expect(resolveHitJourney(hit({ marketplace: 'tz' }))).toBe('TZ_LOCAL');
  });

  it('missing marketplace does not become China', () => {
    expect(resolveHitJourney(hit({ marketplace: null }))).toBeNull();
  });

  it('unknown marketplace does not navigate incorrectly', () => {
    expect(resolveHitJourney(hit({ marketplace: 'global' }))).toBeNull();
  });
});
