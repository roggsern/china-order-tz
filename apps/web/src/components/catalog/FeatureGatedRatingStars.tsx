"use client";

import { useFeatureAvailability } from "@/hooks/use-feature-availability";
import { RatingStars, type RatingStarsProps } from "./RatingStars";

type FeatureGatedRatingStarsProps = RatingStarsProps;

export function FeatureGatedRatingStars(props: FeatureGatedRatingStarsProps) {
  const { reviews, isReady } = useFeatureAvailability();

  if (isReady && !reviews) {
    return null;
  }

  return <RatingStars {...props} />;
}
