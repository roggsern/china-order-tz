import { OfficialLogoImage } from "./OfficialLogoImage";

type LogoMarkProps = {
  className?: string;
  size?: number;
};

/** Renders the official icon-only favicon asset. */
export function LogoMark({ className = "", size = 48 }: LogoMarkProps) {
  return (
    <OfficialLogoImage
      variant="favicon"
      alt=""
      height={size}
      className={className}
    />
  );
}
