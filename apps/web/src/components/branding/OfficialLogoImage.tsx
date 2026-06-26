import { BRAND_ASSETS, type BrandLogoVariant } from "./assets";

type OfficialLogoImageProps = {
  variant: BrandLogoVariant;
  alt?: string;
  className?: string;
  /** Display height in px. Width stays auto to preserve aspect ratio. */
  height?: number;
};

export function OfficialLogoImage({
  variant,
  alt = "CHINA ORDER TZ",
  className = "",
  height = 64,
}: OfficialLogoImageProps) {
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={BRAND_ASSETS[variant]}
      alt={alt}
      height={height}
      className={`block w-auto max-w-none shrink-0 object-contain object-left ${className}`}
      style={{ height, width: "auto" }}
      decoding="async"
      draggable={false}
    />
  );
}
