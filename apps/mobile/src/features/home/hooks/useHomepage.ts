import { useQuery } from '@tanstack/react-query';
import { useJourneyStore } from '@/src/core/auth';
import { fetchHomepageForJourney } from '../api/fetchHomepage';
import {
  EMPTY_LIVE_HOMEPAGE_COMMERCE,
  fetchLiveHomepageCommerce,
  liveHomepageHasContent,
} from '../api/fetchLiveHomepageCommerce';
import { homepageQueryKey, resolveHomepageCommerceContext } from '../map/mapHomepage';
import {
  composeHomepageViewModel,
  emptyCmsHomepageView,
} from '../utils/composeHomepage';

/**
 * Home = journey CMS (with API GLOBAL fallback) + live catalog/store fills
 * + presentation hero/trust when merchandising gaps remain.
 */
export function useHomepage() {
  const journey = useJourneyStore((s) => s.journey);
  const commerceContext = resolveHomepageCommerceContext(journey);

  return useQuery({
    queryKey: [...homepageQueryKey(commerceContext), 'composed'] as const,
    queryFn: async () => {
      const live = await fetchLiveHomepageCommerce(journey).catch(
        () => EMPTY_LIVE_HOMEPAGE_COMMERCE,
      );

      try {
        const cms = await fetchHomepageForJourney(journey, true);
        return composeHomepageViewModel({ cms, live, journey });
      } catch (error) {
        if (liveHomepageHasContent(live)) {
          return composeHomepageViewModel({
            cms: emptyCmsHomepageView(journey),
            live,
            journey,
          });
        }
        throw error;
      }
    },
  });
}
