import { useQuery } from '@tanstack/react-query';
import { useJourneyStore } from '@/src/core/auth';
import { fetchHomepageForJourney } from '../api/fetchHomepage';
import { homepageQueryKey, resolveHomepageCommerceContext } from '../map/mapHomepage';

export function useHomepage() {
  const journey = useJourneyStore((s) => s.journey);
  const commerceContext = resolveHomepageCommerceContext(journey);

  return useQuery({
    queryKey: homepageQueryKey(commerceContext),
    queryFn: () => fetchHomepageForJourney(journey, true),
  });
}
