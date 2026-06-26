/** All 31 regions of Tanzania (mainland + Zanzibar). */
export const TANZANIA_REGIONS = [
  "Arusha",
  "Dar es Salaam",
  "Dodoma",
  "Geita",
  "Iringa",
  "Kagera",
  "Katavi",
  "Kigoma",
  "Kilimanjaro",
  "Lindi",
  "Manyara",
  "Mara",
  "Mbeya",
  "Morogoro",
  "Mtwara",
  "Mwanza",
  "Njombe",
  "Pemba North",
  "Pemba South",
  "Pwani",
  "Rukwa",
  "Ruvuma",
  "Shinyanga",
  "Simiyu",
  "Singida",
  "Songwe",
  "Tabora",
  "Tanga",
  "Zanzibar North",
  "Zanzibar South",
  "Zanzibar West",
] as const;

export type TanzaniaRegion = (typeof TANZANIA_REGIONS)[number];

export function filterTanzaniaRegions(query: string): TanzaniaRegion[] {
  const normalized = query.trim().toLowerCase();
  if (!normalized) {
    return [...TANZANIA_REGIONS];
  }
  return TANZANIA_REGIONS.filter((region) => region.toLowerCase().includes(normalized));
}

export function isValidTanzaniaRegion(value: string): value is TanzaniaRegion {
  return TANZANIA_REGIONS.includes(value as TanzaniaRegion);
}
