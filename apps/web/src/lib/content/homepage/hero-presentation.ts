import type { HomepageHeroSlide } from "./types";

export type HeroContentAlignment = "LEFT" | "CENTER" | "RIGHT";
export type HeroTextTheme = "LIGHT" | "DARK" | "AUTO";

export type HeroBackgroundImages = {
  desktop: string | null;
  mobile: string | null;
  hasImage: boolean;
};

export type HeroResolvedPresentation = {
  alignment: HeroContentAlignment;
  theme: HeroTextTheme;
  textMode: "light-text" | "dark-text";
  background: HeroBackgroundImages;
};

export function normalizeHeroContentAlignment(value?: string | null): HeroContentAlignment {
  const normalized = (value || "CENTER").trim().toUpperCase();
  if (normalized === "LEFT" || normalized === "RIGHT") {
    return normalized;
  }
  return "CENTER";
}

export function normalizeHeroTextTheme(value?: string | null): HeroTextTheme {
  const normalized = (value || "LIGHT").trim().toUpperCase();
  if (normalized === "DARK" || normalized === "AUTO") {
    return normalized;
  }
  return "LIGHT";
}

export function resolveHeroBackgroundImages(slide: Pick<
  HomepageHeroSlide,
  "desktopImageUrl" | "mobileImageUrl"
>): HeroBackgroundImages {
  const desktop = slide.desktopImageUrl?.trim() || null;
  const mobile = slide.mobileImageUrl?.trim() || desktop;

  return {
    desktop,
    mobile,
    hasImage: Boolean(desktop || mobile),
  };
}

export function resolveHeroTextMode(
  theme: HeroTextTheme,
  hasBackgroundImage: boolean,
): "light-text" | "dark-text" {
  if (theme === "DARK") {
    return "dark-text";
  }
  if (theme === "LIGHT") {
    return "light-text";
  }
  // AUTO — photos need a dark scrim with light copy; gradient-only heroes stay light-on-dark.
  return hasBackgroundImage ? "light-text" : "light-text";
}

export function resolveHeroPresentation(slide: HomepageHeroSlide): HeroResolvedPresentation {
  const alignment = slide.contentAlignment ?? "LEFT";
  const theme = slide.textTheme ?? "LIGHT";
  const background = resolveHeroBackgroundImages(slide);

  return {
    alignment,
    theme,
    textMode: resolveHeroTextMode(theme, background.hasImage),
    background,
  };
}

export function heroContentAlignmentClasses(alignment: HeroContentAlignment): {
  row: string;
  block: string;
  ctas: string;
} {
  switch (alignment) {
    case "CENTER":
      return {
        row: "justify-center",
        block: "mx-auto max-w-xl text-center",
        ctas: "sm:justify-center",
      };
    case "RIGHT":
      return {
        row: "justify-end",
        block: "ml-auto max-w-xl text-right",
        ctas: "sm:justify-end",
      };
    case "LEFT":
    default:
      return {
        row: "justify-start",
        block: "mr-auto max-w-xl text-left",
        ctas: "sm:justify-start",
      };
  }
}

export function heroTextThemeClasses(textMode: "light-text" | "dark-text"): {
  title: string;
  description: string;
  eyebrow: string;
  primaryCta: string;
  secondaryCta: string;
  dotActive: string;
  dotIdle: string;
  control: string;
} {
  if (textMode === "dark-text") {
    return {
      title: "text-zinc-900",
      description: "text-zinc-600",
      eyebrow: "text-[#8b6914]",
      primaryCta:
        "bg-[#c9a227] text-zinc-900 hover:bg-[#e8c547]",
      secondaryCta:
        "border-zinc-300/80 bg-white/70 text-zinc-800 hover:border-[#c9a227]/50 hover:bg-white",
      dotActive: "bg-[#8b6914]",
      dotIdle: "bg-zinc-900/25 hover:bg-zinc-900/40",
      control:
        "border-zinc-300/80 bg-white/80 text-zinc-800 backdrop-blur-sm hover:bg-white",
    };
  }

  return {
    title: "text-white",
    description: "text-zinc-300",
    eyebrow: "text-[#e8c547]",
    primaryCta: "bg-[#c9a227] text-zinc-900 hover:bg-[#e8c547]",
    secondaryCta:
      "border-white/20 bg-white/5 text-white hover:border-[#c9a227]/50 hover:bg-white/10",
    dotActive: "bg-[#c9a227]",
    dotIdle: "bg-white/35 hover:bg-white/60",
    control:
      "border-white/15 bg-black/30 text-white backdrop-blur-sm hover:bg-black/50",
  };
}

export function heroReadabilityOverlayClass(
  hasBackgroundImage: boolean,
  textMode: "light-text" | "dark-text",
): string {
  if (!hasBackgroundImage) {
    return "bg-transparent";
  }

  if (textMode === "dark-text") {
    return "bg-gradient-to-r from-white/90 via-white/75 to-white/45";
  }

  return "bg-gradient-to-r from-black/75 via-black/55 to-black/35";
}

export function heroDecorativeOverlayVisible(hasBackgroundImage: boolean): boolean {
  return !hasBackgroundImage;
}
