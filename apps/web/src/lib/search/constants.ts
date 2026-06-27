/** Popular and trending search terms — static suggestions when query is empty or partial match. */
export const TRENDING_SEARCHES = [
  "Wireless earbuds",
  "Women's dresses",
  "Smart watch",
  "Kitchen appliances",
  "Building tiles",
  "Kids toys",
] as const;

export const POPULAR_SEARCHES = [
  "iPhone accessories",
  "Office furniture",
  "Skincare",
  "Men's shoes",
  "LED lights",
  "Baby stroller",
] as const;

export const SEARCH_DEBOUNCE_MS = 300;

export const MAX_PRODUCT_RESULTS = 6;

export const MAX_CATEGORY_RESULTS = 4;

export const MAX_TERM_RESULTS = 4;
