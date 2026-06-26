import { HorizontalBrandLogo } from "@/components/branding/HorizontalBrandLogo";

type BrandLogoProps = {
  className?: string;
  size?: "header" | "sm";
};

/** Renders the official CHINA ORDER TZ header PNG logo. */
export function BrandLogo(props: BrandLogoProps) {
  return <HorizontalBrandLogo {...props} />;
}
