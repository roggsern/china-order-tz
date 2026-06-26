import { OfficialLogoImage } from "./OfficialLogoImage";

type AnimatedLogoProps = {
  className?: string;
  /** Display height in px. Width stays auto. */
  height?: number;
};

/** Version 4 full brand lockup — branding pages only (login, etc.). */
export function AnimatedLogo({ className = "", height = 120 }: AnimatedLogoProps) {
  return (
    <div className={`flex justify-center ${className}`} role="img" aria-label="CHINA ORDER TZ">
      <OfficialLogoImage variant="branding" height={height} className="mx-auto" />
    </div>
  );
}
