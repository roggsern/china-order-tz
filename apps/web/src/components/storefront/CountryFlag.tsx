import type { SVGProps } from "react";

type CountryFlagCode = "CN" | "TZ";

type CountryFlagProps = {
  country: CountryFlagCode;
  /** Visual height in px; width follows natural ratio. Default 18. */
  size?: number;
  className?: string;
  /**
   * When true, flag is decorative (aria-hidden). Prefer true when adjacent
   * visible text already names the journey (avoids duplicated SR text).
   */
  decorative?: boolean;
  /** Used only when decorative is false. */
  title?: string;
};

const RATIO: Record<CountryFlagCode, number> = {
  CN: 3 / 2,
  TZ: 3 / 2,
};

/**
 * Inline SVG country flags for storefront journeys.
 * Do not use OS emoji flags in production UI.
 */
export function CountryFlag({
  country,
  size = 18,
  className = "",
  decorative = true,
  title,
}: CountryFlagProps) {
  const width = Math.round(size * RATIO[country]);
  const shared: SVGProps<SVGSVGElement> = {
    width,
    height: size,
    viewBox: "0 0 36 24",
    className: `inline-block shrink-0 rounded-[2px] align-middle ring-1 ring-black/10 ${className}`,
    role: decorative ? undefined : "img",
    "aria-hidden": decorative ? true : undefined,
    focusable: false,
  };

  if (country === "CN") {
    return (
      <svg {...shared} aria-label={decorative ? undefined : title || "China"}>
        {!decorative ? <title>{title || "China"}</title> : null}
        <rect width="36" height="24" fill="#DE2910" />
        <g fill="#FFDE00">
          <path d="M7.2 3.6 8.4 7.2H12l-3 2.2 1.1 3.5-3-2.2-3 2.2 1.1-3.5-3-2.2h3.6z" />
          <path d="M14.4 2.1l.45 1.35h1.4l-1.15.85.45 1.35-1.15-.85-1.15.85.45-1.35-1.15-.85h1.4z" />
          <path d="M17.7 4.5l.45 1.35h1.4l-1.15.85.45 1.35-1.15-.85-1.15.85.45-1.35-1.15-.85h1.4z" />
          <path d="M17.7 8.4l.45 1.35h1.4l-1.15.85.45 1.35-1.15-.85-1.15.85.45-1.35-1.15-.85h1.4z" />
          <path d="M14.4 10.8l.45 1.35h1.4l-1.15.85.45 1.35-1.15-.85-1.15.85.45-1.35-1.15-.85h1.4z" />
        </g>
      </svg>
    );
  }

  return (
    <svg {...shared} aria-label={decorative ? undefined : title || "Tanzania"}>
      {!decorative ? <title>{title || "Tanzania"}</title> : null}
      {/* Tanzania national flag — green / blue triangles with yellow-edged black diagonal */}
      <rect width="36" height="24" fill="#1EB53A" />
      <polygon points="0,0 36,24 36,0" fill="#00A3DD" />
      <polygon points="0,24 0,19.2 31.2,0 36,0 36,4.8 4.8,24" fill="#FCD116" />
      <polygon points="0,24 0,21.6 32.4,0 36,0 36,2.4 3.6,24" fill="#000000" />
    </svg>
  );
}

export type { CountryFlagCode };
