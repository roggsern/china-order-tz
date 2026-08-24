import { useEffect, useRef, useState } from 'react';
import type { ProductGalleryMediaSlide } from './resolvePdpGalleryMedia';
import {
  galleryImageIdentity,
  planHeldGalleryUpdate,
} from './pdpVariantMedia';
import { prefetchPdpVariantMedia } from './prefetchPdpVariantMedia';

/**
 * Keep currently displayed PDP media until the target first frame is cached.
 * Newer selections increment generation so stale prefetches cannot commit.
 */
export function useHeldPdpGallerySlides(
  target: ProductGalleryMediaSlide[],
): ProductGalleryMediaSlide[] {
  const [committed, setCommitted] = useState(target);
  const committedRef = useRef(committed);
  const generationRef = useRef(0);
  const identity = galleryImageIdentity(target);

  useEffect(() => {
    committedRef.current = committed;
  }, [committed]);

  useEffect(() => {
    const planned = planHeldGalleryUpdate(
      {
        committed: committedRef.current,
        generation: generationRef.current,
      },
      target,
    );
    generationRef.current = planned.generation;

    if (planned.action === 'commit') {
      committedRef.current = planned.state.committed;
      setCommitted(planned.state.committed);
      return;
    }

    let cancelled = false;
    const generation = planned.generation;
    const pendingTarget = target;
    void prefetchPdpVariantMedia(planned.urls).then((ok) => {
      if (cancelled || generation !== generationRef.current) return;
      if (ok || committedRef.current.length === 0) {
        committedRef.current = pendingTarget;
        setCommitted(pendingTarget);
      }
    });

    return () => {
      cancelled = true;
    };
    // Keyed on image/video identity so new array instances with the same
    // URLs do not retrigger prefetch or reset the held frame.
    // eslint-disable-next-line react-hooks/exhaustive-deps -- identity
  }, [identity]);

  return committed;
}
