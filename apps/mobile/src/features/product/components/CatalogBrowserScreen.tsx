import { useJourneyStore } from '@/src/core/auth';
import { ChinaCatalogScreen } from './ChinaCatalogScreen';
import { TzCatalogScreen } from './TzCatalogScreen';
import { browseCatalogKind } from '../utils/buildSafeProductHref';

/** Journey-separated catalog browser for the Browse tab. */
export function CatalogBrowserScreen() {
  const journey = useJourneyStore((s) => s.journey);
  const kind = browseCatalogKind(journey);

  if (kind === 'tz') {
    return <TzCatalogScreen />;
  }

  return <ChinaCatalogScreen />;
}
