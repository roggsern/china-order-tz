import { buyFromTzBrandMenu } from "@/lib/catalog/brands";

export type AdminBrandOption = {
  slug: string;
  name: string;
  origin: "china" | "tz" | "both";
};

const chinaBrandOptions: AdminBrandOption[] = [
  { slug: "china-direct", name: "China Direct", origin: "china" },
  { slug: "guangzhou-oem", name: "Guangzhou OEM", origin: "china" },
  { slug: "shenzhen-tech", name: "Shenzhen Tech", origin: "china" },
  { slug: "yiwu-wholesale", name: "Yiwu Wholesale", origin: "china" },
];

const tzBrandOptions: AdminBrandOption[] = buyFromTzBrandMenu.map((brand) => ({
  slug: brand.slug,
  name: brand.name
    .split(" ")
    .map((word) => word.charAt(0) + word.slice(1).toLowerCase())
    .join(" "),
  origin: "tz" as const,
}));

export const adminBrandOptions: AdminBrandOption[] = [...chinaBrandOptions, ...tzBrandOptions];

export function getAdminBrandBySlug(slug: string): AdminBrandOption | undefined {
  return adminBrandOptions.find((brand) => brand.slug === slug);
}

export function getAdminBrandsForOrigin(origin: "china" | "tz"): AdminBrandOption[] {
  return adminBrandOptions.filter(
    (brand) => brand.origin === origin || brand.origin === "both",
  );
}
