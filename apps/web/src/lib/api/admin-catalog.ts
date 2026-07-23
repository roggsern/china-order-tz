import { enrichApiCategoryFromStatic } from "@/lib/catalog/category-presentation";
import type {
  Category,
  Product,
  ProductImage,
  ProductOrigin,
  ProductStatus,
} from "@/lib/types/catalog";

export class AdminCatalogApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminCatalogApiError";
  }
}

export type AdminApiCategory = {
  id: string;
  department_id?: string | null;
  parent_id?: string | null;
  store_id?: string | null;
  origin?: "china" | "tz" | null;
  product_type_id?: string | null;
  name: string;
  slug: string;
  description?: string | null;
  image?: string | null;
  sort_order?: number;
  is_active?: boolean;
  products_count?: number;
  department?: {
    id: string;
    name: string;
    slug: string;
    icon?: string | null;
  } | null;
  store?: {
    id: string;
    name: string;
    slug: string;
  } | null;
  product_type?: {
    id: string;
    name: string;
    slug: string;
  } | null;
  children?: AdminApiCategory[];
  deleted_at?: string | null;
};

export type AdminApiBrand = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  logo?: string | null;
  banner?: string | null;
  website?: string | null;
  country?: string | null;
  is_featured?: boolean;
  sort_order?: number;
  is_active?: boolean;
  products_count?: number;
  category_ids?: string[];
  categories?: Array<{
    id: string;
    name: string;
    slug: string;
    parent_id?: string | null;
    origin?: "china" | "tz" | null;
  }>;
  deleted_at?: string | null;
};

export type AdminApiDepartment = {
  id: string;
  name: string;
  slug: string;
  icon?: string | null;
  image?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
  deleted_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type AdminApiInventory = {
  quantity?: number | string | null;
  reserved_quantity?: number | string | null;
  available_quantity?: number | string | null;
};

export type AdminApiProductImage = {
  id: string;
  path?: string | null;
  url?: string | null;
  alt_text?: string | null;
  is_primary?: boolean;
  sort_order?: number;
};

export type AdminApiAttributeValue = {
  id: string;
  product_attribute_id?: string;
  value: string;
  slug?: string;
  color_code?: string | null;
  attribute?: { id?: string; name?: string; slug?: string } | null;
};

export type AdminApiPriceTier = {
  id?: string;
  product_id?: string;
  configuration_id?: string | null;
  min_quantity: number;
  tier_type?: string | null;
  unit_price?: string | number | null;
  discount_percent?: string | number | null;
};

export type AdminApiConfiguration = {
  id: string;
  sku?: string | null;
  name?: string | null;
  price?: string | number | null;
  barcode?: string | null;
  attribute_values?: AdminApiAttributeValue[];
  inventory?: AdminApiInventory | null;
  price_tiers?: AdminApiPriceTier[];
};

export type AdminApiCommerceChannel = {
  id: string;
  name: string;
  code: string;
  description?: string | null;
  is_active?: boolean;
  admin_label?: string | null;
  customer_label?: string | null;
};

export type AdminApiProduct = {
  id: string;
  name: string;
  slug: string;
  sku?: string | null;
  description?: string | null;
  short_description?: string | null;
  price: string | number;
  compare_at_price?: string | number | null;
  commerce_channel_id?: string | null;
  fulfillment_source?: string | null;
  commerce_channel?: AdminApiCommerceChannel | null;
  air_shipping_price?: string | number | null;
  sea_shipping_price?: string | number | null;
  shipping_options?: Array<{
    id?: string;
    transport_mode: string;
    price: string | number;
    currency?: string | null;
    is_available?: boolean;
    notes?: string | null;
    sort_order?: number;
  }>;
  weight?: string | number | null;
  is_active?: boolean;
  is_featured?: boolean;
  is_demo?: boolean;
  status?: string | null;
  lifecycle_status?: string | null;
  visibility?: string | null;
  sort_order?: number;
  product_type_id?: string | null;
  catalog_product_type_id?: string | null;
  catalog_product_type?: {
    id?: string | null;
    name?: string | null;
    slug?: string | null;
    subcategory_id?: string | null;
  } | null;
  category?: AdminApiCategory | null;
  brand?: AdminApiBrand | null;
  images?: AdminApiProductImage[];
  inventory?: AdminApiInventory[];
  variants?: AdminApiConfiguration[];
  configurations?: AdminApiConfiguration[];
  price_tiers?: AdminApiPriceTier[];
  deleted_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type AdminCatalogProduct = {
  id: string;
  name: string;
  slug: string;
  sku: string | null;
  shortDescription: string;
  description: string;
  status: "draft" | "active" | "archived" | "out_of_stock";
  visibility: "public" | "private" | "hidden";
  isActive: boolean;
  isFeatured: boolean;
  sortOrder: number;
  brandId: string | null;
  brandName: string | null;
  catalogProductTypeId: string | null;
  catalogProductTypeName: string | null;
  categoryId: string | null;
  categoryName: string | null;
  departmentId: string | null;
  deletedAt: string | null;
};

export type AdminCatalogProductWritePayload = {
  name: string;
  catalog_product_type_id: string;
  brand_id?: string | null;
  sku?: string | null;
  short_description?: string | null;
  description?: string | null;
  status?: "draft" | "active" | "archived";
  visibility?: "public" | "private" | "hidden";
  is_featured?: boolean;
  is_active?: boolean;
  sort_order?: number;
};

export type AdminCatalogProductListParams = {
  page?: number;
  perPage?: number;
  search?: string;
  brandId?: string;
  departmentId?: string;
  categoryId?: string;
  subcategoryId?: string;
  catalogProductTypeId?: string;
  status?: string;
  featured?: boolean;
  trashed?: boolean;
};

export type AdminCategory = Category & {
  id: string;
  departmentId?: string | null;
  departmentName?: string | null;
  departmentIcon?: string | null;
  parentId?: string | null;
  storeId?: string | null;
  origin?: ProductOrigin | null;
  productTypeId?: string | null;
  productTypeName?: string | null;
  image?: string | null;
  sortOrder?: number;
  isActive: boolean;
  productsCount: number;
  deletedAt?: string | null;
};

export type AdminBrand = {
  id: string;
  slug: string;
  name: string;
  description: string;
  icon: string;
  isActive: boolean;
  isFeatured: boolean;
  sortOrder: number;
  productsCount: number;
  logo?: string | null;
  banner?: string | null;
  website?: string | null;
  country?: string | null;
  categoryIds: string[];
  categories: Array<{
    id: string;
    name: string;
    slug: string;
    parentId?: string | null;
    origin?: ProductOrigin | null;
  }>;
  deletedAt?: string | null;
};

export type AdminDepartment = {
  id: string;
  slug: string;
  name: string;
  description: string;
  icon: string;
  image?: string | null;
  sortOrder: number;
  isActive: boolean;
  deletedAt?: string | null;
};

type PaginatedPayload<T> = {
  success?: boolean;
  message?: string;
  data?: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
  links?: unknown;
};

export type AdminProductListParams = {
  page?: number;
  perPage?: number;
  search?: string;
  category?: string;
  brand?: string;
  status?: "0" | "1" | string;
  sort?: "name" | "price" | "created_at" | "sort_order";
  direction?: "asc" | "desc";
  brandId?: string;
  departmentId?: string;
  categoryId?: string;
  subcategoryId?: string;
  catalogProductTypeId?: string;
  featured?: boolean;
  trashed?: boolean;
};

const DEFAULT_GRADIENT = "from-zinc-800 via-zinc-700 to-zinc-900";
const DEFAULT_EMOJI = "🛍️";

function apiIdToNumericId(id: string): number {
  let hash = 0;

  for (let index = 0; index < id.length; index += 1) {
    hash = (hash * 31 + id.charCodeAt(index)) >>> 0;
  }

  return hash || 1;
}

function parseMoney(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === "") {
    return 0;
  }

  const parsed = typeof value === "number" ? value : Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function parseOptionalMoney(value: string | number | null | undefined): number | undefined {
  const parsed = parseMoney(value);
  return parsed > 0 ? parsed : undefined;
}

function parseStock(inventory: AdminApiInventory[] | undefined): number {
  if (!inventory?.length) {
    return 0;
  }

  return inventory.reduce((sum, row) => {
    const available = row.available_quantity ?? row.quantity ?? 0;
    const parsed = typeof available === "number" ? available : Number.parseFloat(String(available));
    return sum + (Number.isFinite(parsed) ? parsed : 0);
  }, 0);
}

function mapPriceTiers(
  tiers: AdminApiPriceTier[] | undefined,
): import("@/lib/types/catalog").ProductPriceTierDraft[] {
  if (!tiers?.length) {
    return [];
  }

  return tiers.map((tier) => {
    const tierType =
      tier.tier_type === "percent_off" ? ("percent_off" as const) : ("fixed_unit" as const);
    const unitRaw = tier.unit_price;
    const percentRaw = tier.discount_percent;

    return {
      id: tier.id,
      minQuantity: Number(tier.min_quantity) || 1,
      tierType,
      unitPrice:
        unitRaw === null || unitRaw === undefined || unitRaw === ""
          ? null
          : parseMoney(unitRaw),
      discountPercent:
        percentRaw === null || percentRaw === undefined || percentRaw === ""
          ? null
          : Number(percentRaw),
    };
  });
}

function mapConfigurations(
  product: AdminApiProduct,
): import("@/lib/types/catalog").ProductConfigurationDraft[] {
  const rows = product.configurations ?? product.variants ?? [];

  return rows.map((row) => {
    const quantity = row.inventory?.available_quantity ?? row.inventory?.quantity ?? 0;
    const stock =
      typeof quantity === "number" ? quantity : Number.parseInt(String(quantity), 10) || 0;
    const priceRaw = row.price;
    const price =
      priceRaw === null || priceRaw === undefined || priceRaw === ""
        ? null
        : parseMoney(priceRaw);

    return {
      id: row.id,
      attributeValueIds: (row.attribute_values ?? []).map((value) => value.id),
      label:
        row.name?.trim() ||
        (row.attribute_values ?? []).map((value) => value.value).join(" / ") ||
        row.sku ||
        "Configuration",
      sku: row.sku?.trim() || "",
      stock,
      price,
      barcode: row.barcode?.trim() || "",
      priceTiers: mapPriceTiers(row.price_tiers),
    };
  });
}

function mapStatus(
  lifecycleStatus: string | null | undefined,
  isActive: boolean | undefined,
): ProductStatus {
  const normalized = lifecycleStatus?.trim().toLowerCase();
  if (
    normalized === "draft" ||
    normalized === "active" ||
    normalized === "out_of_stock" ||
    normalized === "archived"
  ) {
    return normalized;
  }

  return isActive === false ? "archived" : "active";
}

function mapOrigin(product: AdminApiProduct): ProductOrigin {
  const code = product.commerce_channel?.code?.toUpperCase();
  if (code === "TZ_LOCAL") return "tz";
  if (code === "CHINA_IMPORT") return "china";

  if (product.fulfillment_source === "buy_from_tz") return "tz";
  if (product.fulfillment_source === "imported_from_china") return "china";

  const air = parseOptionalMoney(product.air_shipping_price);
  const sea = parseOptionalMoney(product.sea_shipping_price);

  if (air || sea) {
    return "china";
  }

  return "tz";
}

function mapImage(
  image: AdminApiProductImage,
  productName: string,
  index: number,
): ProductImage | undefined {
  const rawSrc = image.url?.trim() || image.path?.trim();

  if (!rawSrc || !image.id) {
    return undefined;
  }

  return {
    id: apiIdToNumericId(image.id),
    catalogImageId: image.id,
    emoji: DEFAULT_EMOJI,
    gradient: DEFAULT_GRADIENT,
    alt: image.alt_text?.trim() || `${productName} image ${index + 1}`,
    url: rawSrc,
    path: image.path?.trim() || undefined,
  };
}

/** Maps Laravel admin ProductResource → storefront/admin Product type. */
export function mapAdminApiProductToProduct(product: AdminApiProduct): Product {
  const airCost = parseOptionalMoney(product.air_shipping_price);
  const seaCost = parseOptionalMoney(product.sea_shipping_price);
  const rawShippingOptions = product.shipping_options ?? [];
  const shippingOptions = rawShippingOptions
    .filter((option) => option.is_available !== false)
    .map((option) => ({
      type: (option.transport_mode === "sea" ? "sea" : "air") as "air" | "sea",
      price: parseMoney(option.price),
    }));
  const airOption = rawShippingOptions.find((option) => option.transport_mode === "air");
  const seaOption = rawShippingOptions.find((option) => option.transport_mode === "sea");
  const images = (product.images ?? [])
    .map((image, index) => mapImage(image, product.name, index))
    .filter((image): image is ProductImage => Boolean(image));
  const primaryImage =
    images.find((_, index) => product.images?.[index]?.is_primary) ?? images[0];
  const configurations = mapConfigurations(product);
  const productPriceTiers = mapPriceTiers(
    (product.price_tiers ?? []).filter((tier) => !tier.configuration_id),
  );
  const hasConfigurations = configurations.length > 0;
  const configurationStock = configurations.reduce((sum, row) => sum + row.stock, 0);
  const stock = hasConfigurations ? configurationStock : parseStock(product.inventory);
  const price = parseMoney(product.price);
  const oldPrice = parseOptionalMoney(product.compare_at_price) ?? 0;

  return {
    id: apiIdToNumericId(product.id),
    catalogProductId: product.id,
    categoryId: product.category?.id,
    parentCategoryId: product.category?.parent_id ?? undefined,
    brandId: product.brand?.id,
    slug: product.slug,
    name: product.name,
    description: product.short_description?.trim() || product.description?.trim() || product.name,
    shortDescription: product.short_description?.trim() || undefined,
    fullDescription: product.description?.trim() || undefined,
    price,
    oldPrice,
    rating: 0,
    reviews: 0,
    badge: product.is_featured ? "Featured" : "",
    badges: product.is_featured ? ["PREMIUM"] : [],
    trustBadges: product.is_featured ? ["Premium"] : [],
    origin: mapOrigin(product),
    type: mapOrigin(product) === "china" ? "china" : "local",
    brand: product.brand?.name,
    brandSlug: product.brand?.slug,
    gradient: DEFAULT_GRADIENT,
    emoji: DEFAULT_EMOJI,
    categorySlug: product.category?.slug ?? "uncategorized",
    stock,
    weightKg: parseOptionalMoney(product.weight),
    sku: product.sku?.trim() || undefined,
    airCost: airOption ? parseMoney(airOption.price) : airCost,
    seaCost: seaOption ? parseMoney(seaOption.price) : seaCost,
    shippingOptions: shippingOptions.length > 0 ? shippingOptions : undefined,
    // Stash availability for form mapping via shippingOptions presence + legacy costs.
    primary_image: primaryImage,
    images,
    image: primaryImage?.url ?? primaryImage?.path,
    thumbnailImageId: primaryImage?.id,
    features: [],
    specifications: [],
    customerReviews: [],
    featured: Boolean(product.is_featured),
    status: mapStatus(product.lifecycle_status, product.is_active),
    isDemo: Boolean(product.is_demo),
    priceTiers: productPriceTiers,
    createdAt: product.created_at ?? undefined,
    configurations,
  };
}

export function mapAdminApiCategory(category: AdminApiCategory): AdminCategory {
  const presentation = enrichApiCategoryFromStatic({
    slug: category.slug,
    name: category.name,
  });

  return {
    ...presentation,
    description: category.description?.trim() || presentation.description,
    id: category.id,
    departmentId: category.department_id ?? category.department?.id ?? null,
    departmentName: category.department?.name ?? null,
    departmentIcon: category.department?.icon ?? null,
    parentId: category.parent_id ?? null,
    storeId: category.store_id ?? category.store?.id ?? null,
    origin: category.origin ?? null,
    productTypeId: category.product_type_id ?? null,
    productTypeName: category.product_type?.name ?? null,
    image: category.image ?? null,
    sortOrder: category.sort_order ?? 0,
    isActive: category.is_active !== false,
    productsCount: category.products_count ?? 0,
    deletedAt: category.deleted_at ?? null,
  };
}

export function mapAdminApiBrand(brand: AdminApiBrand): AdminBrand {
  return {
    id: brand.id,
    slug: brand.slug,
    name: brand.name,
    description: brand.description?.trim() || "",
    icon: "✨",
    isActive: brand.is_active !== false,
    isFeatured: brand.is_featured === true,
    sortOrder: brand.sort_order ?? 0,
    productsCount: brand.products_count ?? 0,
    logo: brand.logo,
    banner: brand.banner,
    website: brand.website,
    country: brand.country,
    categoryIds: brand.category_ids ?? brand.categories?.map((item) => item.id) ?? [],
    categories: (brand.categories ?? []).map((item) => ({
      id: item.id,
      name: item.name,
      slug: item.slug,
      parentId: item.parent_id ?? null,
      origin: item.origin ?? null,
    })),
    deletedAt: brand.deleted_at ?? null,
  };
}

export function mapAdminApiDepartment(department: AdminApiDepartment): AdminDepartment {
  return {
    id: department.id,
    slug: department.slug,
    name: department.name,
    description: department.description?.trim() || "",
    icon: department.icon?.trim() || "🏬",
    image: department.image ?? null,
    sortOrder: department.sort_order ?? 0,
    isActive: department.is_active !== false,
    deletedAt: department.deleted_at ?? null,
  };
}

async function parseJsonResponse<T>(response: Response): Promise<T> {
  const raw = await response.text();
  let payload: T;

  try {
    payload = JSON.parse(raw) as T;
  } catch {
    throw new AdminCatalogApiError(
      response.status === 404
        ? "Admin catalog API route was not found."
        : "Unexpected response from the admin catalog API.",
      response.status,
    );
  }

  return payload;
}

async function fetchAllPaginated<T, R>(
  path: string,
  mapItem: (item: T) => R,
  query?: Record<string, string | number | undefined>,
): Promise<{ items: R[]; total: number }> {
  const items: R[] = [];
  let page = 1;
  let lastPage = 1;
  let total = 0;

  do {
    const params = new URLSearchParams();
    params.set("page", String(page));
    params.set("per_page", "100");

    if (query) {
      for (const [key, value] of Object.entries(query)) {
        if (value !== undefined && value !== "" && key !== "page" && key !== "per_page") {
          params.set(key, String(value));
        }
      }
    }

    const response = await fetch(`${path}?${params.toString()}`, {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    const payload = await parseJsonResponse<PaginatedPayload<T>>(response);

    if (!response.ok || payload.success === false) {
      throw new AdminCatalogApiError(
        payload.message?.trim() || "Unable to load admin catalog data.",
        response.status,
      );
    }

    const pageItems = payload.data ?? [];
    items.push(...pageItems.map(mapItem));
    lastPage = payload.meta?.last_page ?? 1;
    total = payload.meta?.total ?? items.length;
    page += 1;
  } while (page <= lastPage);

  return { items, total };
}

/**
 * Loads admin products from the BFF (all pages).
 * Optional search/category/brand/status are forwarded to Laravel.
 */
export async function fetchAdminProducts(
  params: AdminProductListParams = {},
): Promise<{ products: Product[]; total: number }> {
  const { items, total } = await fetchAllPaginated(
    "/api/admin/products",
    mapAdminApiProductToProduct,
    {
      search: params.search,
      category: params.category,
      brand: params.brand,
      status: params.status,
      sort: params.sort,
      direction: params.direction,
    },
  );

  return { products: items, total };
}

export type AdminCategoryListParams = {
  departmentId?: string;
  origin?: "china" | "tz";
  parentId?: string | null;
  rootsOnly?: boolean;
  search?: string;
  isActive?: boolean;
  trashed?: boolean;
};

export async function fetchAdminCategories(
  params: AdminCategoryListParams = {},
): Promise<AdminCategory[]> {
  const query: Record<string, string | number | undefined> = {};

  if (params.departmentId) {
    query.department_id = params.departmentId;
  }
  if (params.origin) {
    query.origin = params.origin;
  }
  if (params.rootsOnly) {
    query.roots_only = 1;
  }
  if (params.parentId === null) {
    query.parent_id = "null";
  } else if (params.parentId) {
    query.parent_id = params.parentId;
  }
  if (params.search) {
    query.search = params.search;
  }
  if (params.isActive === true) {
    query.is_active = 1;
  }
  if (params.isActive === false) {
    query.is_active = 0;
  }
  if (params.trashed) {
    query.trashed = 1;
  }

  const { items } = await fetchAllPaginated<AdminApiCategory, AdminCategory>(
    "/api/admin/categories",
    mapAdminApiCategory,
    query,
  );
  return items;
}

export type AdminBrandListParams = {
  categoryId?: string;
  search?: string;
  isActive?: boolean;
  isFeatured?: boolean;
  trashed?: boolean;
  page?: number;
  perPage?: number;
};

export async function fetchAdminBrands(
  params: AdminBrandListParams = {},
): Promise<AdminBrand[]> {
  const query: Record<string, string | number | undefined> = {};
  if (params.categoryId) query.category_id = params.categoryId;
  if (params.search) query.search = params.search;
  if (params.isActive === true) query.is_active = 1;
  if (params.isActive === false) query.is_active = 0;
  if (params.isFeatured === true) query.is_featured = 1;
  if (params.isFeatured === false) query.is_featured = 0;
  if (params.trashed) query.trashed = 1;

  const { items } = await fetchAllPaginated<AdminApiBrand, AdminBrand>(
    "/api/admin/brands",
    mapAdminApiBrand,
    query,
  );
  return items;
}

export async function fetchAdminBrandsPage(
  params: AdminBrandListParams = {},
): Promise<{ items: AdminBrand[]; total: number; lastPage: number; page: number }> {
  const page = params.page && params.page > 0 ? params.page : 1;
  const perPage = params.perPage && params.perPage > 0 ? params.perPage : 15;

  const searchParams = new URLSearchParams();
  searchParams.set("page", String(page));
  searchParams.set("per_page", String(perPage));
  if (params.categoryId) searchParams.set("category_id", params.categoryId);
  if (params.search) searchParams.set("search", params.search);
  if (params.isActive === true) searchParams.set("is_active", "1");
  if (params.isActive === false) searchParams.set("is_active", "0");
  if (params.isFeatured === true) searchParams.set("is_featured", "1");
  if (params.isFeatured === false) searchParams.set("is_featured", "0");
  if (params.trashed) searchParams.set("trashed", "1");

  const response = await fetch(`/api/admin/brands?${searchParams.toString()}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<PaginatedPayload<AdminApiBrand>>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load brands.",
      response.status,
    );
  }

  return {
    items: (payload.data ?? []).map(mapAdminApiBrand),
    total: payload.meta?.total ?? 0,
    lastPage: payload.meta?.last_page ?? 1,
    page: payload.meta?.current_page ?? page,
  };
}

export type AdminCategoryWritePayload = {
  name: string;
  department_id: string;
  slug?: string | null;
  parent_id?: string | null;
  origin: "china" | "tz";
  store_id?: string | null;
  product_type_id?: string | null;
  image?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
};

export type AdminBrandWritePayload = {
  name: string;
  description?: string | null;
  logo?: string | null;
  banner?: string | null;
  website?: string | null;
  country?: string | null;
  is_featured?: boolean;
  sort_order?: number;
  is_active?: boolean;
  category_ids?: string[];
};

export type AdminDepartmentListParams = {
  search?: string;
  isActive?: boolean;
  trashed?: boolean;
};

export type AdminDepartmentWritePayload = {
  name: string;
  slug?: string | null;
  icon?: string | null;
  image?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
};

export async function fetchAdminDepartments(
  params: AdminDepartmentListParams = {},
): Promise<AdminDepartment[]> {
  const query: Record<string, string | number | undefined> = {};
  if (params.search) query.search = params.search;
  if (params.isActive === true) query.is_active = 1;
  if (params.isActive === false) query.is_active = 0;
  if (params.trashed) query.trashed = 1;

  const { items } = await fetchAllPaginated<AdminApiDepartment, AdminDepartment>(
    "/api/admin/departments",
    mapAdminApiDepartment,
    query,
  );
  return items;
}

export type AdminApiStore = {
  id: string;
  code?: string;
  name: string;
  slug: string;
  is_active?: boolean;
  storefront_enabled?: boolean;
  storefront_visible?: boolean;
  deleted_at?: string | null;
};

export type AdminStoreOption = {
  id: string;
  name: string;
  slug: string;
  code: string;
  isActive: boolean;
};

/** Active, non-deleted stores from Admin Stores API (DB-backed). */
export async function fetchAdminStores(): Promise<AdminStoreOption[]> {
  const response = await fetch("/api/admin/stores", {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiStore[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load stores from the API.",
      response.status,
    );
  }

  const rows = Array.isArray(payload.data) ? payload.data : [];

  return rows
    .filter((store) => store.is_active !== false && !store.deleted_at)
    .map((store) => ({
      id: store.id,
      name: store.name,
      slug: store.slug,
      code: store.code?.trim() || "",
      isActive: store.is_active !== false,
    }))
    .sort((a, b) => a.name.localeCompare(b.name));
}

export async function createAdminDepartment(
  payload: AdminDepartmentWritePayload,
): Promise<AdminDepartment> {
  const data = await mutateAdminJson<AdminApiDepartment>(
    "/api/admin/departments",
    "POST",
    payload,
    "Unable to create department.",
  );
  return mapAdminApiDepartment(data);
}

export async function updateAdminDepartment(
  id: string,
  payload: AdminDepartmentWritePayload,
): Promise<AdminDepartment> {
  const data = await mutateAdminJson<AdminApiDepartment>(
    `/api/admin/departments/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update department.",
  );
  return mapAdminApiDepartment(data);
}

export async function deleteAdminDepartment(id: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/departments/${encodeURIComponent(id)}`,
    "DELETE",
    undefined,
    "Unable to delete department.",
  );
}

export async function restoreAdminDepartment(id: string): Promise<AdminDepartment> {
  const data = await mutateAdminJson<AdminApiDepartment>(
    `/api/admin/departments/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore department.",
  );
  return mapAdminApiDepartment(data);
}

export type AdminApiSubcategory = {
  id: string;
  category_id: string;
  department_id?: string | null;
  name: string;
  slug: string;
  description?: string | null;
  image?: string | null;
  sort_order?: number;
  is_active?: boolean;
  products_count?: number;
  category?: {
    id: string;
    name: string;
    slug: string;
    department_id?: string | null;
  } | null;
  department?: {
    id: string;
    name: string;
    slug: string;
    icon?: string | null;
  } | null;
  deleted_at?: string | null;
};

export type AdminSubcategory = {
  id: string;
  categoryId: string;
  categoryName: string;
  categorySlug: string;
  departmentId: string | null;
  departmentName: string | null;
  departmentIcon: string | null;
  name: string;
  slug: string;
  description: string;
  image: string | null;
  sortOrder: number;
  isActive: boolean;
  productsCount: number;
  deletedAt?: string | null;
};

export function mapAdminApiSubcategory(subcategory: AdminApiSubcategory): AdminSubcategory {
  return {
    id: subcategory.id,
    categoryId: subcategory.category_id,
    categoryName: subcategory.category?.name ?? "",
    categorySlug: subcategory.category?.slug ?? "",
    departmentId: subcategory.department_id ?? subcategory.department?.id ?? null,
    departmentName: subcategory.department?.name ?? null,
    departmentIcon: subcategory.department?.icon ?? null,
    name: subcategory.name,
    slug: subcategory.slug,
    description: subcategory.description?.trim() || "",
    image: subcategory.image ?? null,
    sortOrder: subcategory.sort_order ?? 0,
    isActive: subcategory.is_active !== false,
    productsCount: subcategory.products_count ?? 0,
    deletedAt: subcategory.deleted_at ?? null,
  };
}

export type AdminSubcategoryListParams = {
  departmentId?: string;
  categoryId?: string;
  search?: string;
  isActive?: boolean;
  trashed?: boolean;
};

export type AdminSubcategoryWritePayload = {
  name: string;
  category_id: string;
  slug?: string | null;
  image?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
};

export async function fetchAdminSubcategories(
  params: AdminSubcategoryListParams = {},
): Promise<AdminSubcategory[]> {
  const query: Record<string, string | number | undefined> = {};
  if (params.departmentId) query.department_id = params.departmentId;
  if (params.categoryId) query.category_id = params.categoryId;
  if (params.search) query.search = params.search;
  if (params.isActive === true) query.is_active = 1;
  if (params.isActive === false) query.is_active = 0;
  if (params.trashed) query.trashed = 1;

  const { items } = await fetchAllPaginated<AdminApiSubcategory, AdminSubcategory>(
    "/api/admin/subcategories",
    mapAdminApiSubcategory,
    query,
  );
  return items;
}

export async function createAdminSubcategory(
  payload: AdminSubcategoryWritePayload,
): Promise<AdminSubcategory> {
  const data = await mutateAdminJson<AdminApiSubcategory>(
    "/api/admin/subcategories",
    "POST",
    payload,
    "Unable to create subcategory.",
  );
  return mapAdminApiSubcategory(data);
}

export async function updateAdminSubcategory(
  id: string,
  payload: AdminSubcategoryWritePayload,
): Promise<AdminSubcategory> {
  const data = await mutateAdminJson<AdminApiSubcategory>(
    `/api/admin/subcategories/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update subcategory.",
  );
  return mapAdminApiSubcategory(data);
}

export async function deleteAdminSubcategory(id: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/subcategories/${encodeURIComponent(id)}`,
    "DELETE",
    undefined,
    "Unable to delete subcategory.",
  );
}

export async function restoreAdminSubcategory(id: string): Promise<AdminSubcategory> {
  const data = await mutateAdminJson<AdminApiSubcategory>(
    `/api/admin/subcategories/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore subcategory.",
  );
  return mapAdminApiSubcategory(data);
}

export type AdminApiCatalogProductType = {
  id: string;
  subcategory_id: string;
  name: string;
  slug: string;
  image?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
  products_count?: number;
  attributes_count?: number;
  subcategory?: {
    id: string;
    name: string;
    slug: string;
    parent_id?: string | null;
    department_id?: string | null;
  } | null;
  category?: {
    id: string;
    name: string;
    slug: string;
    department_id?: string | null;
  } | null;
  department?: {
    id: string;
    name: string;
    slug: string;
    icon?: string | null;
  } | null;
  deleted_at?: string | null;
};

export type AdminCatalogProductType = {
  id: string;
  subcategoryId: string;
  subcategoryName: string;
  categoryId: string | null;
  categoryName: string | null;
  departmentId: string | null;
  departmentName: string | null;
  departmentIcon: string | null;
  name: string;
  slug: string;
  description: string;
  image: string | null;
  sortOrder: number;
  isActive: boolean;
  productsCount: number;
  attributesCount: number;
  deletedAt?: string | null;
};

export function mapAdminApiCatalogProductType(
  item: AdminApiCatalogProductType,
): AdminCatalogProductType {
  return {
    id: item.id,
    subcategoryId: item.subcategory_id,
    subcategoryName: item.subcategory?.name ?? "",
    categoryId: item.category?.id ?? item.subcategory?.parent_id ?? null,
    categoryName: item.category?.name ?? null,
    departmentId: item.department?.id ?? null,
    departmentName: item.department?.name ?? null,
    departmentIcon: item.department?.icon ?? null,
    name: item.name,
    slug: item.slug,
    description: item.description?.trim() || "",
    image: item.image ?? null,
    sortOrder: item.sort_order ?? 0,
    isActive: item.is_active !== false,
    productsCount: item.products_count ?? 0,
    attributesCount: item.attributes_count ?? 0,
    deletedAt: item.deleted_at ?? null,
  };
}

export type AdminCatalogProductTypeListParams = {
  departmentId?: string;
  categoryId?: string;
  subcategoryId?: string;
  search?: string;
  isActive?: boolean;
  trashed?: boolean;
};

export type AdminCatalogProductTypeWritePayload = {
  name: string;
  subcategory_id: string;
  slug?: string | null;
  image?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
};

export async function fetchAdminCatalogProductTypes(
  params: AdminCatalogProductTypeListParams = {},
): Promise<AdminCatalogProductType[]> {
  const query: Record<string, string | number | undefined> = {};
  if (params.departmentId) query.department_id = params.departmentId;
  if (params.categoryId) query.category_id = params.categoryId;
  if (params.subcategoryId) query.subcategory_id = params.subcategoryId;
  if (params.search) query.search = params.search;
  if (params.isActive === true) query.is_active = 1;
  if (params.isActive === false) query.is_active = 0;
  if (params.trashed) query.trashed = 1;

  const { items } = await fetchAllPaginated<
    AdminApiCatalogProductType,
    AdminCatalogProductType
  >("/api/admin/catalog-product-types", mapAdminApiCatalogProductType, query);
  return items;
}

/** Active Configuration Templates (legacy product_types / ProductType). */
export type AdminConfigurationTemplate = {
  id: string;
  name: string;
  slug: string;
  hasConfigurations: boolean;
};

export async function fetchAdminConfigurationTemplates(): Promise<
  AdminConfigurationTemplate[]
> {
  const response = await fetch("/api/admin/product-types", {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: Array<{
      id: string;
      name: string;
      slug: string;
      has_configurations?: boolean;
      is_active?: boolean;
    }>;
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load Configuration Templates.",
      response.status,
    );
  }

  return (payload.data ?? [])
    .filter((item) => item.is_active !== false)
    .map((item) => ({
      id: item.id,
      name: item.name,
      slug: item.slug,
      hasConfigurations: item.has_configurations !== false,
    }));
}

export async function createAdminCatalogProductType(
  payload: AdminCatalogProductTypeWritePayload,
): Promise<AdminCatalogProductType> {
  const data = await mutateAdminJson<AdminApiCatalogProductType>(
    "/api/admin/catalog-product-types",
    "POST",
    payload,
    "Unable to create product type.",
  );
  return mapAdminApiCatalogProductType(data);
}

export async function updateAdminCatalogProductType(
  id: string,
  payload: AdminCatalogProductTypeWritePayload,
): Promise<AdminCatalogProductType> {
  const data = await mutateAdminJson<AdminApiCatalogProductType>(
    `/api/admin/catalog-product-types/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update product type.",
  );
  return mapAdminApiCatalogProductType(data);
}

export async function deleteAdminCatalogProductType(id: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/catalog-product-types/${encodeURIComponent(id)}`,
    "DELETE",
    undefined,
    "Unable to delete product type.",
  );
}

export async function restoreAdminCatalogProductType(
  id: string,
): Promise<AdminCatalogProductType> {
  const data = await mutateAdminJson<AdminApiCatalogProductType>(
    `/api/admin/catalog-product-types/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore product type.",
  );
  return mapAdminApiCatalogProductType(data);
}

export type CatalogAttributeType =
  | "text"
  | "number"
  | "boolean"
  | "select"
  | "multiselect";

export type AdminApiCatalogAttributeOption = {
  id: string;
  catalog_attribute_id: string;
  value: string;
  slug: string;
  sort_order?: number;
};

export type AdminApiCatalogAttribute = {
  id: string;
  name: string;
  slug: string;
  type: CatalogAttributeType;
  unit?: string | null;
  is_filterable?: boolean;
  is_required?: boolean;
  sort_order?: number;
  is_active?: boolean;
  options?: AdminApiCatalogAttributeOption[];
  catalog_product_types?: Array<{
    id: string;
    name: string;
    slug: string;
    is_required?: boolean;
    sort_order?: number;
  }>;
  deleted_at?: string | null;
};

export type AdminCatalogAttributeOption = {
  id: string;
  attributeId: string;
  value: string;
  slug: string;
  sortOrder: number;
};

export type AdminCatalogAttribute = {
  id: string;
  name: string;
  slug: string;
  type: CatalogAttributeType;
  unit: string | null;
  isFilterable: boolean;
  isRequired: boolean;
  sortOrder: number;
  isActive: boolean;
  options: AdminCatalogAttributeOption[];
  catalogProductTypeIds: string[];
  deletedAt?: string | null;
};

export function mapAdminApiCatalogAttribute(
  item: AdminApiCatalogAttribute,
): AdminCatalogAttribute {
  return {
    id: item.id,
    name: item.name,
    slug: item.slug,
    type: item.type,
    unit: item.unit ?? null,
    isFilterable: item.is_filterable === true,
    isRequired: item.is_required === true,
    sortOrder: item.sort_order ?? 0,
    isActive: item.is_active !== false,
    options: (item.options ?? []).map((option) => ({
      id: option.id,
      attributeId: option.catalog_attribute_id,
      value: option.value,
      slug: option.slug,
      sortOrder: option.sort_order ?? 0,
    })),
    catalogProductTypeIds: (item.catalog_product_types ?? []).map((type) => type.id),
    deletedAt: item.deleted_at ?? null,
  };
}

export type AdminCatalogAttributeListParams = {
  search?: string;
  type?: CatalogAttributeType;
  isFilterable?: boolean;
  isActive?: boolean;
  trashed?: boolean;
};

export type AdminCatalogAttributeWritePayload = {
  name: string;
  slug?: string | null;
  type: CatalogAttributeType;
  unit?: string | null;
  is_filterable?: boolean;
  is_required?: boolean;
  sort_order?: number;
  is_active?: boolean;
  options?: Array<{ value: string; slug?: string; sort_order?: number }>;
};

export async function fetchAdminCatalogAttributes(
  params: AdminCatalogAttributeListParams = {},
): Promise<AdminCatalogAttribute[]> {
  const query: Record<string, string | number | undefined> = {};
  if (params.search) query.search = params.search;
  if (params.type) query.type = params.type;
  if (params.isFilterable === true) query.is_filterable = 1;
  if (params.isFilterable === false) query.is_filterable = 0;
  if (params.isActive === true) query.is_active = 1;
  if (params.isActive === false) query.is_active = 0;
  if (params.trashed) query.trashed = 1;

  const { items } = await fetchAllPaginated<
    AdminApiCatalogAttribute,
    AdminCatalogAttribute
  >("/api/admin/catalog-attributes", mapAdminApiCatalogAttribute, query);
  return items;
}

export async function createAdminCatalogAttribute(
  payload: AdminCatalogAttributeWritePayload,
): Promise<AdminCatalogAttribute> {
  const data = await mutateAdminJson<AdminApiCatalogAttribute>(
    "/api/admin/catalog-attributes",
    "POST",
    payload,
    "Unable to create attribute.",
  );
  return mapAdminApiCatalogAttribute(data);
}

export async function updateAdminCatalogAttribute(
  id: string,
  payload: AdminCatalogAttributeWritePayload,
): Promise<AdminCatalogAttribute> {
  const data = await mutateAdminJson<AdminApiCatalogAttribute>(
    `/api/admin/catalog-attributes/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update attribute.",
  );
  return mapAdminApiCatalogAttribute(data);
}

export async function deleteAdminCatalogAttribute(id: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/catalog-attributes/${encodeURIComponent(id)}`,
    "DELETE",
    undefined,
    "Unable to delete attribute.",
  );
}

export async function restoreAdminCatalogAttribute(
  id: string,
): Promise<AdminCatalogAttribute> {
  const data = await mutateAdminJson<AdminApiCatalogAttribute>(
    `/api/admin/catalog-attributes/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore attribute.",
  );
  return mapAdminApiCatalogAttribute(data);
}

export async function createAdminCatalogAttributeOption(
  attributeId: string,
  payload: { value: string; slug?: string | null; sort_order?: number },
): Promise<AdminCatalogAttributeOption> {
  const data = await mutateAdminJson<AdminApiCatalogAttributeOption>(
    `/api/admin/catalog-attributes/${encodeURIComponent(attributeId)}/options`,
    "POST",
    payload,
    "Unable to create option.",
  );
  return {
    id: data.id,
    attributeId: data.catalog_attribute_id,
    value: data.value,
    slug: data.slug,
    sortOrder: data.sort_order ?? 0,
  };
}

export async function updateAdminCatalogAttributeOption(
  optionId: string,
  payload: { value: string; slug?: string | null; sort_order?: number },
): Promise<AdminCatalogAttributeOption> {
  const data = await mutateAdminJson<AdminApiCatalogAttributeOption>(
    `/api/admin/catalog-attribute-options/${encodeURIComponent(optionId)}`,
    "PUT",
    payload,
    "Unable to update option.",
  );
  return {
    id: data.id,
    attributeId: data.catalog_attribute_id,
    value: data.value,
    slug: data.slug,
    sortOrder: data.sort_order ?? 0,
  };
}

export async function deleteAdminCatalogAttributeOption(optionId: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/catalog-attribute-options/${encodeURIComponent(optionId)}`,
    "DELETE",
    undefined,
    "Unable to delete option.",
  );
}

export async function syncAdminCatalogProductTypeAttributes(
  catalogProductTypeId: string,
  attributes: Array<{
    catalog_attribute_id: string;
    is_required?: boolean;
    sort_order?: number;
  }>,
): Promise<AdminCatalogAttribute[]> {
  const response = await fetch(
    `/api/admin/catalog-product-types/${encodeURIComponent(catalogProductTypeId)}/attributes`,
    {
      method: "PUT",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ attributes }),
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: { attributes?: AdminApiCatalogAttribute[] };
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false) {
    const fieldError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;
    throw new AdminCatalogApiError(
      fieldError || payload.message?.trim() || "Unable to sync attributes.",
      response.status,
    );
  }

  return (payload.data?.attributes ?? []).map(mapAdminApiCatalogAttribute);
}

export async function fetchAdminCatalogFilters(
  catalogProductTypeId?: string,
): Promise<AdminCatalogAttribute[]> {
  const query = catalogProductTypeId
    ? `?catalog_product_type_id=${encodeURIComponent(catalogProductTypeId)}`
    : "";
  const response = await fetch(`/api/admin/catalog-attributes/filters${query}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiCatalogAttribute[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load filters.",
      response.status,
    );
  }

  return (payload.data ?? []).map(mapAdminApiCatalogAttribute);
}

async function mutateAdminJson<T>(
  path: string,
  method: string,
  body?: unknown,
  errorFallback = "Unable to save catalog data.",
): Promise<T> {
  const response = await fetch(path, {
    method,
    headers: {
      Accept: "application/json",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: T;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false) {
    const fieldError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;
    throw new AdminCatalogApiError(
      fieldError || payload.message?.trim() || errorFallback,
      response.status,
    );
  }

  return (payload.data ?? null) as T;
}

export async function createAdminCategory(
  payload: AdminCategoryWritePayload,
): Promise<AdminCategory> {
  const data = await mutateAdminJson<AdminApiCategory>(
    "/api/admin/categories",
    "POST",
    payload,
    "Unable to create category.",
  );
  return mapAdminApiCategory(data);
}

export async function updateAdminCategory(
  id: string,
  payload: AdminCategoryWritePayload,
): Promise<AdminCategory> {
  const data = await mutateAdminJson<AdminApiCategory>(
    `/api/admin/categories/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update category.",
  );
  return mapAdminApiCategory(data);
}

export async function deleteAdminCategory(id: string): Promise<void> {
  await mutateAdminJson(`/api/admin/categories/${encodeURIComponent(id)}`, "DELETE", undefined, "Unable to delete category.");
}

export async function restoreAdminCategory(id: string): Promise<AdminCategory> {
  const data = await mutateAdminJson<AdminApiCategory>(
    `/api/admin/categories/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore category.",
  );
  return mapAdminApiCategory(data);
}

export async function createAdminBrand(
  payload: AdminBrandWritePayload,
): Promise<AdminBrand> {
  const data = await mutateAdminJson<AdminApiBrand>(
    "/api/admin/brands",
    "POST",
    payload,
    "Unable to create brand.",
  );
  return mapAdminApiBrand(data);
}

export async function updateAdminBrand(
  id: string,
  payload: AdminBrandWritePayload,
): Promise<AdminBrand> {
  const data = await mutateAdminJson<AdminApiBrand>(
    `/api/admin/brands/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update brand.",
  );
  return mapAdminApiBrand(data);
}

export async function deleteAdminBrand(id: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/brands/${encodeURIComponent(id)}`,
    "DELETE",
    undefined,
    "Unable to delete brand.",
  );
}

export async function restoreAdminBrand(id: string): Promise<AdminBrand> {
  const data = await mutateAdminJson<AdminApiBrand>(
    `/api/admin/brands/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore brand.",
  );
  return mapAdminApiBrand(data);
}

export async function syncAdminBrandCategories(
  id: string,
  categoryIds: string[],
): Promise<AdminBrand> {
  const data = await mutateAdminJson<AdminApiBrand>(
    `/api/admin/brands/${encodeURIComponent(id)}/categories`,
    "PUT",
    { category_ids: categoryIds },
    "Unable to update brand category links.",
  );
  return mapAdminApiBrand(data);
}

export async function uploadAdminBrandAsset(
  brandId: string,
  field: "logo" | "banner",
  file: File,
): Promise<AdminBrand> {
  const formData = new FormData();
  formData.append("field", field);
  formData.append("file", file, file.name);

  const response = await fetch(
    `/api/admin/brands/${encodeURIComponent(brandId)}/assets`,
    {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiBrand;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false || !payload.data?.id) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    throw new AdminCatalogApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to upload brand asset.",
      response.status,
    );
  }

  return mapAdminApiBrand(payload.data);
}

export async function deleteAdminProduct(catalogProductId: string): Promise<void> {
  const response = await fetch(`/api/admin/products/${encodeURIComponent(catalogProductId)}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{ success?: boolean; message?: string }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to delete product.",
      response.status,
    );
  }
}

function mapTierDraftToPayload(
  tier: import("@/lib/types/catalog").ProductPriceTierDraft,
): {
  min_quantity: number;
  tier_type: "fixed_unit" | "percent_off";
  unit_price?: number | null;
  discount_percent?: number | null;
} {
  if (tier.tierType === "percent_off") {
    return {
      min_quantity: Math.max(1, Math.floor(tier.minQuantity || 1)),
      tier_type: "percent_off",
      discount_percent: Math.max(0, Math.min(100, Number(tier.discountPercent) || 0)),
      unit_price: null,
    };
  }

  return {
    min_quantity: Math.max(1, Math.floor(tier.minQuantity || 1)),
    tier_type: "fixed_unit",
    unit_price: Math.max(0, Number(tier.unitPrice) || 0),
    discount_percent: null,
  };
}

/** Laravel POST /api/v1/admin/products body (via BFF). */
export type AdminProductCreatePayload = {
  name: string;
  slug?: string | null;
  category_id: string;
  brand_id?: string | null;
  commerce_channel_id?: string | null;
  sku: string;
  price: number;
  compare_at_price?: number | null;
  air_shipping_price?: number | null;
  sea_shipping_price?: number | null;
  shipping_options?: Array<{
    transport_mode: "air" | "sea";
    price: number;
    currency?: string;
    is_available?: boolean;
    notes?: string | null;
    sort_order?: number;
  }>;
  weight?: number | null;
  stock_quantity: number;
  short_description?: string | null;
  description?: string | null;
  is_featured?: boolean;
  is_demo?: boolean;
  lifecycle_status: "draft" | "active" | "out_of_stock" | "archived";
  price_tiers?: Array<{
    min_quantity: number;
    tier_type: "fixed_unit" | "percent_off";
    unit_price?: number | null;
    discount_percent?: number | null;
  }>;
  configurations?: Array<{
    id?: string;
    attribute_value_ids: string[];
    sku?: string | null;
    stock_quantity: number;
    price?: number | null;
    barcode?: string | null;
    price_tiers?: Array<{
      min_quantity: number;
      tier_type: "fixed_unit" | "percent_off";
      unit_price?: number | null;
      discount_percent?: number | null;
    }>;
  }>;
};

export function commerceChannelCodeForProductForm(
  data: Pick<import("@/lib/types/catalog").ProductFormData, "type" | "origin">,
): "CHINA_IMPORT" | "TZ_LOCAL" {
  if (data.type === "local" || data.origin === "tz") {
    return "TZ_LOCAL";
  }

  return "CHINA_IMPORT";
}

export async function fetchAdminCommerceChannels(): Promise<AdminApiCommerceChannel[]> {
  const response = await fetch("/api/admin/commerce-channels", {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiCommerceChannel[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load commerce channels.",
      response.status,
    );
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

async function resolveCommerceChannelIdForForm(
  data: import("@/lib/types/catalog").ProductFormData,
): Promise<string | null> {
  const code = commerceChannelCodeForProductForm(data);
  const channels = await fetchAdminCommerceChannels();
  const match = channels.find((channel) => channel.code === code);

  return match?.id ?? null;
}

export function productFormDataToCreatePayload(
  data: import("@/lib/types/catalog").ProductFormData,
  commerceChannelId?: string | null,
): AdminProductCreatePayload {
  const description =
    data.fullDescription?.trim() || data.description?.trim() || null;
  const shortDescription = data.shortDescription?.trim() || null;
  const hasConfigurations = data.configurations.length > 0;
  const lifecycleStatus =
    data.status === "hidden"
      ? "archived"
      : data.status === "draft" ||
          data.status === "active" ||
          data.status === "out_of_stock" ||
          data.status === "archived"
        ? data.status
        : "active";

  const productTiers =
    !hasConfigurations && data.wholesaleEnabled
      ? data.priceTiers.filter((tier) => tier.minQuantity >= 1).map(mapTierDraftToPayload)
      : [];

  return {
    name: data.name.trim(),
    slug: data.slug.trim() || null,
    category_id: data.categoryId,
    brand_id: data.brandId?.trim() ? data.brandId.trim() : null,
    commerce_channel_id: commerceChannelId ?? null,
    sku: data.sku.trim() || "",
    price: data.price,
    compare_at_price: data.oldPrice > 0 ? data.oldPrice : null,
    air_shipping_price:
      data.type === "china" && data.airAvailable && data.airCost > 0 ? data.airCost : null,
    sea_shipping_price:
      data.type === "china" && data.seaAvailable && data.seaCost > 0 ? data.seaCost : null,
    shipping_options:
      data.type === "china"
        ? [
            ...(data.airAvailable && data.airCost > 0
              ? [
                  {
                    transport_mode: "air" as const,
                    price: data.airCost,
                    currency: "TZS",
                    is_available: true,
                    notes: data.airNotes.trim() || null,
                    sort_order: 0,
                  },
                ]
              : []),
            ...(data.seaAvailable && data.seaCost > 0
              ? [
                  {
                    transport_mode: "sea" as const,
                    price: data.seaCost,
                    currency: "TZS",
                    is_available: true,
                    notes: data.seaNotes.trim() || null,
                    sort_order: 1,
                  },
                ]
              : []),
          ]
        : [],
    weight: data.weightKg != null && data.weightKg > 0 ? data.weightKg : null,
    stock_quantity: hasConfigurations ? 0 : Math.max(0, Math.floor(data.stock)),
    short_description: shortDescription,
    description,
    is_featured: Boolean(data.featured),
    is_demo: Boolean(data.isDemo),
    lifecycle_status: lifecycleStatus,
    price_tiers: hasConfigurations ? [] : productTiers,
    configurations: hasConfigurations
      ? data.configurations.map((row) => ({
          id: row.id,
          attribute_value_ids: row.attributeValueIds,
          sku: row.sku.trim() || null,
          stock_quantity: Math.max(0, Math.floor(row.stock)),
          price: row.price != null && row.price > 0 ? row.price : null,
          barcode: row.barcode.trim() || null,
          price_tiers: (row.priceTiers ?? [])
            .filter((tier) => tier.minQuantity >= 1)
            .map(mapTierDraftToPayload),
        }))
      : undefined,
  };
}

export function mapAdminApiCatalogProduct(product: AdminApiProduct): AdminCatalogProduct {
  const statusRaw = product.status ?? product.lifecycle_status ?? "draft";
  const status =
    statusRaw === "active" ||
    statusRaw === "draft" ||
    statusRaw === "archived" ||
    statusRaw === "out_of_stock"
      ? statusRaw
      : "draft";

  const visibilityRaw = product.visibility ?? "public";
  const visibility =
    visibilityRaw === "private" || visibilityRaw === "hidden" ? visibilityRaw : "public";

  return {
    id: product.id,
    name: product.name,
    slug: product.slug,
    sku: product.sku ?? null,
    shortDescription: product.short_description?.trim() || "",
    description: product.description?.trim() || "",
    status,
    visibility,
    isActive: product.is_active !== false,
    isFeatured: product.is_featured === true,
    sortOrder: product.sort_order ?? 0,
    brandId: product.brand?.id ?? null,
    brandName: product.brand?.name ?? null,
    catalogProductTypeId:
      product.catalog_product_type_id ?? product.catalog_product_type?.id ?? null,
    catalogProductTypeName: product.catalog_product_type?.name ?? null,
    categoryId: product.category?.id ?? null,
    categoryName: product.category?.name ?? null,
    departmentId: product.category?.department_id ?? null,
    deletedAt: product.deleted_at ?? null,
  };
}

export async function fetchAdminCatalogProductsPage(
  params: AdminCatalogProductListParams = {},
): Promise<{ items: AdminCatalogProduct[]; total: number; lastPage: number; page: number }> {
  const page = params.page && params.page > 0 ? params.page : 1;
  const perPage = params.perPage && params.perPage > 0 ? params.perPage : 15;
  const searchParams = new URLSearchParams();
  searchParams.set("page", String(page));
  searchParams.set("per_page", String(perPage));
  if (params.search) searchParams.set("search", params.search);
  if (params.brandId) searchParams.set("brand_id", params.brandId);
  if (params.departmentId) searchParams.set("department_id", params.departmentId);
  if (params.categoryId) searchParams.set("category_id", params.categoryId);
  if (params.subcategoryId) searchParams.set("subcategory_id", params.subcategoryId);
  if (params.catalogProductTypeId) {
    searchParams.set("catalog_product_type_id", params.catalogProductTypeId);
  }
  if (params.status) searchParams.set("status", params.status);
  if (params.featured === true) searchParams.set("featured", "1");
  if (params.featured === false) searchParams.set("featured", "0");
  if (params.trashed) searchParams.set("trashed", "1");

  const response = await fetch(`/api/admin/products?${searchParams.toString()}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<PaginatedPayload<AdminApiProduct>>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load products.",
      response.status,
    );
  }

  return {
    items: (payload.data ?? []).map(mapAdminApiCatalogProduct),
    total: payload.meta?.total ?? 0,
    lastPage: payload.meta?.last_page ?? 1,
    page: payload.meta?.current_page ?? page,
  };
}

export async function createAdminCatalogProduct(
  payload: AdminCatalogProductWritePayload,
): Promise<AdminCatalogProduct> {
  const data = await mutateAdminJson<AdminApiProduct>(
    "/api/admin/products",
    "POST",
    payload,
    "Unable to create product.",
  );
  return mapAdminApiCatalogProduct(data);
}

export async function updateAdminCatalogProduct(
  id: string,
  payload: AdminCatalogProductWritePayload,
): Promise<AdminCatalogProduct> {
  const data = await mutateAdminJson<AdminApiProduct>(
    `/api/admin/products/${encodeURIComponent(id)}`,
    "PUT",
    payload,
    "Unable to update product.",
  );
  return mapAdminApiCatalogProduct(data);
}

export async function deleteAdminCatalogProduct(id: string): Promise<void> {
  await mutateAdminJson(
    `/api/admin/products/${encodeURIComponent(id)}`,
    "DELETE",
    undefined,
    "Unable to delete product.",
  );
}

export async function restoreAdminCatalogProduct(id: string): Promise<AdminCatalogProduct> {
  const data = await mutateAdminJson<AdminApiProduct>(
    `/api/admin/products/${encodeURIComponent(id)}/restore`,
    "POST",
    undefined,
    "Unable to restore product.",
  );
  return mapAdminApiCatalogProduct(data);
}

export type AdminProductMedia = {
  id: string;
  productId: string;
  type: "image" | "video";
  url: string;
  thumbnailUrl: string | null;
  altText: string;
  title: string;
  sortOrder: number;
  isPrimary: boolean;
  isActive: boolean;
};

type AdminApiProductMedia = {
  id: string;
  product_id: string;
  type: "image" | "video" | string;
  url: string;
  thumbnail_url?: string | null;
  alt_text?: string | null;
  title?: string | null;
  sort_order?: number;
  is_primary?: boolean;
  is_active?: boolean;
};

export function mapAdminApiProductMedia(item: AdminApiProductMedia): AdminProductMedia {
  return {
    id: item.id,
    productId: item.product_id,
    type: item.type === "video" ? "video" : "image",
    url: item.url,
    thumbnailUrl: item.thumbnail_url ?? null,
    altText: item.alt_text?.trim() || "",
    title: item.title?.trim() || "",
    sortOrder: item.sort_order ?? 0,
    isPrimary: item.is_primary === true,
    isActive: item.is_active !== false,
  };
}

export async function fetchAdminProductMedia(productId: string): Promise<AdminProductMedia[]> {
  const response = await fetch(
    `/api/admin/products/${encodeURIComponent(productId)}/media`,
    { headers: { Accept: "application/json" }, cache: "no-store" },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiProductMedia[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load product media.",
      response.status,
    );
  }

  return (payload.data ?? []).map(mapAdminApiProductMedia);
}

export async function uploadAdminProductMediaImage(
  productId: string,
  file: File,
  options: {
    altText?: string;
    title?: string;
    isPrimary?: boolean;
    sortOrder?: number;
  } = {},
): Promise<AdminProductMedia> {
  const formData = new FormData();
  formData.append("type", "image");
  formData.append("file", file, file.name);
  if (options.altText) formData.append("alt_text", options.altText);
  if (options.title) formData.append("title", options.title);
  if (options.isPrimary !== undefined) {
    formData.append("is_primary", options.isPrimary ? "1" : "0");
  }
  if (options.sortOrder !== undefined) {
    formData.append("sort_order", String(options.sortOrder));
  }

  const response = await fetch(
    `/api/admin/products/${encodeURIComponent(productId)}/media`,
    {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiProductMedia;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false || !payload.data?.id) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;
    throw new AdminCatalogApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to upload image.",
      response.status,
    );
  }

  return mapAdminApiProductMedia(payload.data);
}

export async function createAdminProductMediaVideo(
  productId: string,
  url: string,
  options: { title?: string; altText?: string } = {},
): Promise<AdminProductMedia> {
  const data = await mutateAdminJson<AdminApiProductMedia>(
    `/api/admin/products/${encodeURIComponent(productId)}/media`,
    "POST",
    {
      type: "video",
      url,
      title: options.title ?? null,
      alt_text: options.altText ?? null,
    },
    "Unable to add video.",
  );
  return mapAdminApiProductMedia(data);
}

export async function updateAdminProductMedia(
  productId: string,
  mediaId: string,
  payload: {
    alt_text?: string | null;
    title?: string | null;
    sort_order?: number;
    is_active?: boolean;
    is_primary?: boolean;
    url?: string | null;
  },
): Promise<AdminProductMedia> {
  const data = await mutateAdminJson<AdminApiProductMedia>(
    `/api/admin/products/${encodeURIComponent(productId)}/media/${encodeURIComponent(mediaId)}`,
    "PUT",
    payload,
    "Unable to update media.",
  );
  return mapAdminApiProductMedia(data);
}

export async function deleteAdminProductMedia(
  productId: string,
  mediaId: string,
): Promise<void> {
  await mutateAdminJson(
    `/api/admin/products/${encodeURIComponent(productId)}/media/${encodeURIComponent(mediaId)}`,
    "DELETE",
    undefined,
    "Unable to delete media.",
  );
}

export async function setAdminProductMediaPrimary(
  productId: string,
  mediaId: string,
): Promise<AdminProductMedia> {
  const data = await mutateAdminJson<AdminApiProductMedia>(
    `/api/admin/products/${encodeURIComponent(productId)}/media/${encodeURIComponent(mediaId)}/primary`,
    "POST",
    undefined,
    "Unable to set primary media.",
  );
  return mapAdminApiProductMedia(data);
}

export type AdminProductSpecAttribute = {
  catalogAttributeId: string;
  name: string;
  slug: string;
  type: CatalogAttributeType;
  unit: string | null;
  isRequired: boolean;
  options: Array<{ id: string; value: string; slug: string }>;
  value: {
    valueText: string | null;
    valueNumber: number | null;
    valueBoolean: boolean | null;
    optionId: string | null;
    optionIds: string[];
    display: string | null;
  };
};

type AdminApiProductSpecAttribute = {
  catalog_attribute_id: string;
  name: string;
  slug: string;
  type: CatalogAttributeType | string;
  unit?: string | null;
  is_required?: boolean;
  options?: Array<{ id: string; value: string; slug: string }>;
  value?: {
    value_text?: string | null;
    value_number?: number | null;
    value_boolean?: boolean | null;
    option_id?: string | null;
    option_ids?: string[];
    display?: string | null;
  };
};

export function mapAdminApiProductSpecAttribute(
  item: AdminApiProductSpecAttribute,
): AdminProductSpecAttribute {
  const type = (
    ["text", "number", "boolean", "select", "multiselect"] as const
  ).includes(item.type as CatalogAttributeType)
    ? (item.type as CatalogAttributeType)
    : "text";

  return {
    catalogAttributeId: item.catalog_attribute_id,
    name: item.name,
    slug: item.slug,
    type,
    unit: item.unit ?? null,
    isRequired: item.is_required === true,
    options: item.options ?? [],
    value: {
      valueText: item.value?.value_text ?? null,
      valueNumber:
        item.value?.value_number === null || item.value?.value_number === undefined
          ? null
          : Number(item.value.value_number),
      valueBoolean:
        item.value?.value_boolean === null || item.value?.value_boolean === undefined
          ? null
          : Boolean(item.value.value_boolean),
      optionId: item.value?.option_id ?? null,
      optionIds: item.value?.option_ids ?? [],
      display: item.value?.display ?? null,
    },
  };
}

export async function fetchAdminProductSpecifications(
  productId: string,
): Promise<AdminProductSpecAttribute[]> {
  const response = await fetch(
    `/api/admin/products/${encodeURIComponent(productId)}/attributes`,
    { headers: { Accept: "application/json" }, cache: "no-store" },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiProductSpecAttribute[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load specifications.",
      response.status,
    );
  }

  return (payload.data ?? []).map(mapAdminApiProductSpecAttribute);
}

export type AdminProductSpecWriteRow = {
  catalog_attribute_id: string;
  value_text?: string | null;
  value_number?: number | null;
  value_boolean?: boolean | null;
  option_id?: string | null;
  option_ids?: string[];
};

export async function syncAdminProductSpecifications(
  productId: string,
  attributes: AdminProductSpecWriteRow[],
): Promise<AdminProductSpecAttribute[]> {
  const data = await mutateAdminJson<AdminApiProductSpecAttribute[]>(
    `/api/admin/products/${encodeURIComponent(productId)}/attributes`,
    "PUT",
    { attributes },
    "Unable to save specifications.",
  );

  return (data ?? []).map(mapAdminApiProductSpecAttribute);
}

export type AdminProductVariantAttributeValue = {
  id: string;
  catalogAttributeId: string;
  attributeName: string | null;
  attributeSlug: string | null;
  type: string;
  optionId: string | null;
  optionValue: string | null;
  valueText: string | null;
  valueNumber: number | null;
  valueBoolean: boolean | null;
  display: string | null;
};

export type AdminProductVariant = {
  id: string;
  productId: string;
  name: string | null;
  sku: string;
  barcode: string | null;
  status: "active" | "inactive";
  isActive: boolean;
  isDefault: boolean;
  sortOrder: number;
  price: number | null;
  stock: number | null;
  pricesCount: number;
  inventoriesCount: number;
  attributeValues: AdminProductVariantAttributeValue[];
};

export type AdminVariantAttributeOption = {
  id: string;
  value: string;
  slug: string;
};

export type AdminVariantAttribute = {
  catalogAttributeId: string;
  name: string;
  slug: string;
  type: string;
  options: AdminVariantAttributeOption[];
};

export type AdminProductVariantsPayload = {
  variants: AdminProductVariant[];
  attributes: AdminVariantAttribute[];
  createdCount?: number;
};

type AdminApiVariantAttributeValue = {
  id: string;
  catalog_attribute_id: string;
  attribute_name?: string | null;
  attribute_slug?: string | null;
  type?: string;
  option_id?: string | null;
  option_value?: string | null;
  value_text?: string | null;
  value_number?: number | null;
  value_boolean?: boolean | null;
  display?: string | null;
};

type AdminApiProductVariant = {
  id: string;
  product_id: string;
  name?: string | null;
  sku: string;
  barcode?: string | null;
  status?: string;
  is_active?: boolean;
  is_default?: boolean;
  sort_order?: number;
  price?: number | null;
  stock?: number | null;
  prices_count?: number;
  inventories_count?: number;
  attribute_values?: AdminApiVariantAttributeValue[];
};

type AdminApiVariantAttribute = {
  catalog_attribute_id: string;
  name: string;
  slug: string;
  type?: string;
  options?: Array<{ id: string; value: string; slug: string }>;
};

type AdminApiProductVariantsPayload = {
  variants?: AdminApiProductVariant[];
  attributes?: AdminApiVariantAttribute[];
  created_count?: number;
};

export function mapAdminApiProductVariant(item: AdminApiProductVariant): AdminProductVariant {
  return {
    id: item.id,
    productId: item.product_id,
    name: item.name ?? null,
    sku: item.sku,
    barcode: item.barcode ?? null,
    status: item.status === "inactive" || item.is_active === false ? "inactive" : "active",
    isActive: item.is_active !== false && item.status !== "inactive",
    isDefault: item.is_default === true,
    sortOrder: Number(item.sort_order ?? 0),
    price: item.price === null || item.price === undefined ? null : Number(item.price),
    stock: item.stock === null || item.stock === undefined ? null : Number(item.stock),
    pricesCount: Number(item.prices_count ?? 0),
    inventoriesCount: Number(item.inventories_count ?? 0),
    attributeValues: (item.attribute_values ?? []).map((row) => ({
      id: row.id,
      catalogAttributeId: row.catalog_attribute_id,
      attributeName: row.attribute_name ?? null,
      attributeSlug: row.attribute_slug ?? null,
      type: row.type ?? "text",
      optionId: row.option_id ?? null,
      optionValue: row.option_value ?? null,
      valueText: row.value_text ?? null,
      valueNumber:
        row.value_number === null || row.value_number === undefined
          ? null
          : Number(row.value_number),
      valueBoolean:
        row.value_boolean === null || row.value_boolean === undefined
          ? null
          : Boolean(row.value_boolean),
      display: row.display ?? null,
    })),
  };
}

function mapAdminApiProductVariantsPayload(
  data: AdminApiProductVariantsPayload | null | undefined,
): AdminProductVariantsPayload {
  return {
    variants: (data?.variants ?? []).map(mapAdminApiProductVariant),
    attributes: (data?.attributes ?? []).map((attr) => ({
      catalogAttributeId: attr.catalog_attribute_id,
      name: attr.name,
      slug: attr.slug,
      type: attr.type ?? "select",
      options: attr.options ?? [],
    })),
    createdCount:
      data?.created_count === undefined || data?.created_count === null
        ? undefined
        : Number(data.created_count),
  };
}

export async function fetchAdminProductVariants(
  productId: string,
): Promise<AdminProductVariantsPayload> {
  const response = await fetch(
    `/api/admin/products/${encodeURIComponent(productId)}/variants`,
    { headers: { Accept: "application/json" }, cache: "no-store" },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiProductVariantsPayload;
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load variants.",
      response.status,
    );
  }

  return mapAdminApiProductVariantsPayload(payload.data);
}

export type AdminProductVariantWritePayload = {
  name?: string | null;
  sku?: string | null;
  barcode?: string | null;
  status?: "active" | "inactive";
  is_default?: boolean;
  sort_order?: number;
  price?: number | null;
  attribute_values?: Array<{
    catalog_attribute_id: string;
    option_id?: string | null;
    value_text?: string | null;
    value_number?: number | null;
    value_boolean?: boolean | null;
  }>;
};

export async function createAdminProductVariant(
  productId: string,
  body: AdminProductVariantWritePayload,
): Promise<AdminProductVariant> {
  const data = await mutateAdminJson<AdminApiProductVariant>(
    `/api/admin/products/${encodeURIComponent(productId)}/variants`,
    "POST",
    body,
    "Unable to create variant.",
  );
  return mapAdminApiProductVariant(data);
}

export async function updateAdminProductVariant(
  productId: string,
  variantId: string,
  body: AdminProductVariantWritePayload,
): Promise<AdminProductVariant> {
  const data = await mutateAdminJson<AdminApiProductVariant>(
    `/api/admin/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variantId)}`,
    "PUT",
    body,
    "Unable to update variant.",
  );
  return mapAdminApiProductVariant(data);
}

export async function deleteAdminProductVariant(
  productId: string,
  variantId: string,
): Promise<void> {
  await mutateAdminJson<null>(
    `/api/admin/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variantId)}`,
    "DELETE",
    undefined,
    "Unable to delete variant.",
  );
}

export async function generateAdminProductVariants(
  productId: string,
  body: {
    attributes: Array<{ catalog_attribute_id: string; option_ids: string[] }>;
    replace_existing?: boolean;
  },
): Promise<AdminProductVariantsPayload> {
  const data = await mutateAdminJson<AdminApiProductVariantsPayload>(
    `/api/admin/products/${encodeURIComponent(productId)}/variants/generate`,
    "POST",
    body,
    "Unable to generate variants.",
  );
  return mapAdminApiProductVariantsPayload(data);
}

export type VariantPriceType = "retail" | "wholesale" | "dealer" | "vip";

export type AdminVariantPrice = {
  id: string;
  productVariantId: string;
  priceType: VariantPriceType;
  currency: string;
  amount: number;
  compareAtPrice: number | null;
  costPrice: number | null;
  minimumQuantity: number;
  isActive: boolean;
  isCurrentlyActive: boolean;
  startsAt: string | null;
  endsAt: string | null;
};

type AdminApiVariantPrice = {
  id: string;
  product_variant_id: string;
  price_type: string;
  currency: string;
  amount: number;
  compare_at_price?: number | null;
  cost_price?: number | null;
  minimum_quantity?: number;
  is_active?: boolean;
  is_currently_active?: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
};

function mapVariantPriceType(value: string): VariantPriceType {
  if (value === "wholesale" || value === "dealer" || value === "vip") {
    return value;
  }
  return "retail";
}

export function mapAdminApiVariantPrice(item: AdminApiVariantPrice): AdminVariantPrice {
  return {
    id: item.id,
    productVariantId: item.product_variant_id,
    priceType: mapVariantPriceType(item.price_type),
    currency: item.currency,
    amount: Number(item.amount),
    compareAtPrice:
      item.compare_at_price === null || item.compare_at_price === undefined
        ? null
        : Number(item.compare_at_price),
    costPrice:
      item.cost_price === null || item.cost_price === undefined
        ? null
        : Number(item.cost_price),
    minimumQuantity: Number(item.minimum_quantity ?? 1),
    isActive: item.is_active !== false,
    isCurrentlyActive: item.is_currently_active === true,
    startsAt: item.starts_at ?? null,
    endsAt: item.ends_at ?? null,
  };
}

export type AdminVariantPriceWritePayload = {
  price_type?: VariantPriceType;
  currency?: string;
  amount?: number;
  compare_at_price?: number | null;
  cost_price?: number | null;
  minimum_quantity?: number;
  is_active?: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
};

export async function fetchAdminVariantPrices(
  variantId: string,
): Promise<AdminVariantPrice[]> {
  const response = await fetch(
    `/api/admin/variants/${encodeURIComponent(variantId)}/prices`,
    { headers: { Accept: "application/json" }, cache: "no-store" },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiVariantPrice[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load prices.",
      response.status,
    );
  }

  return (payload.data ?? []).map(mapAdminApiVariantPrice);
}

export async function createAdminVariantPrice(
  variantId: string,
  body: AdminVariantPriceWritePayload,
): Promise<AdminVariantPrice> {
  const data = await mutateAdminJson<AdminApiVariantPrice>(
    `/api/admin/variants/${encodeURIComponent(variantId)}/prices`,
    "POST",
    body,
    "Unable to create price.",
  );
  return mapAdminApiVariantPrice(data);
}

export async function updateAdminVariantPrice(
  priceId: string,
  body: AdminVariantPriceWritePayload,
): Promise<AdminVariantPrice> {
  const data = await mutateAdminJson<AdminApiVariantPrice>(
    `/api/admin/prices/${encodeURIComponent(priceId)}`,
    "PUT",
    body,
    "Unable to update price.",
  );
  return mapAdminApiVariantPrice(data);
}

export async function deleteAdminVariantPrice(priceId: string): Promise<void> {
  await mutateAdminJson<null>(
    `/api/admin/prices/${encodeURIComponent(priceId)}`,
    "DELETE",
    undefined,
    "Unable to delete price.",
  );
}

export type AdminVariantInventory = {
  id: string;
  productVariantId: string;
  warehouseCode: string;
  onHand: number;
  reserved: number;
  available: number;
  reorderLevel: number;
  safetyStock: number;
  needsReorder: boolean;
  isActive: boolean;
};

type AdminApiVariantInventory = {
  id: string;
  product_variant_id: string;
  warehouse_code: string;
  on_hand: number;
  reserved: number;
  available: number;
  reorder_level?: number;
  safety_stock?: number;
  needs_reorder?: boolean;
  is_active?: boolean;
};

export function mapAdminApiVariantInventory(
  item: AdminApiVariantInventory,
): AdminVariantInventory {
  return {
    id: item.id,
    productVariantId: item.product_variant_id,
    warehouseCode: item.warehouse_code,
    onHand: Number(item.on_hand),
    reserved: Number(item.reserved),
    available: Number(item.available),
    reorderLevel: Number(item.reorder_level ?? 5),
    safetyStock: Number(item.safety_stock ?? 0),
    needsReorder: item.needs_reorder === true,
    isActive: item.is_active !== false,
  };
}

export type AdminVariantInventoryWritePayload = {
  warehouse_code?: string;
  on_hand?: number;
  reserved?: number;
  reorder_level?: number;
  safety_stock?: number;
  is_active?: boolean;
  reserve?: number;
  release?: number;
};

export async function fetchAdminVariantInventories(
  variantId: string,
): Promise<AdminVariantInventory[]> {
  const response = await fetch(
    `/api/admin/variants/${encodeURIComponent(variantId)}/inventory`,
    { headers: { Accept: "application/json" }, cache: "no-store" },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiVariantInventory[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load inventory.",
      response.status,
    );
  }

  return (payload.data ?? []).map(mapAdminApiVariantInventory);
}

export async function createAdminVariantInventory(
  variantId: string,
  body: AdminVariantInventoryWritePayload,
): Promise<AdminVariantInventory> {
  const data = await mutateAdminJson<AdminApiVariantInventory>(
    `/api/admin/variants/${encodeURIComponent(variantId)}/inventory`,
    "POST",
    body,
    "Unable to create inventory.",
  );
  return mapAdminApiVariantInventory(data);
}

export async function updateAdminVariantInventory(
  inventoryId: string,
  body: AdminVariantInventoryWritePayload,
): Promise<AdminVariantInventory> {
  const data = await mutateAdminJson<AdminApiVariantInventory>(
    `/api/admin/inventory/${encodeURIComponent(inventoryId)}`,
    "PUT",
    body,
    "Unable to update inventory.",
  );
  return mapAdminApiVariantInventory(data);
}

export async function deleteAdminVariantInventory(
  inventoryId: string,
): Promise<void> {
  await mutateAdminJson<null>(
    `/api/admin/inventory/${encodeURIComponent(inventoryId)}`,
    "DELETE",
    undefined,
    "Unable to delete inventory.",
  );
}

export async function createAdminProduct(
  data: import("@/lib/types/catalog").ProductFormData,
): Promise<Product> {
  const commerceChannelId = await resolveCommerceChannelIdForForm(data);
  const body = productFormDataToCreatePayload(data, commerceChannelId);

  const response = await fetch("/api/admin/products", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiProduct;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false || !payload.data?.id) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    throw new AdminCatalogApiError(
      firstError?.trim() ||
        payload.message?.trim() ||
        "Unable to create product.",
      response.status,
    );
  }

  return mapAdminApiProductToProduct(payload.data);
}

export async function updateAdminProduct(
  catalogProductId: string,
  data: import("@/lib/types/catalog").ProductFormData,
): Promise<Product> {
  const commerceChannelId = await resolveCommerceChannelIdForForm(data);
  const body = productFormDataToCreatePayload(data, commerceChannelId);

  const response = await fetch(`/api/admin/products/${encodeURIComponent(catalogProductId)}`, {
    method: "PUT",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminApiProduct;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false || !payload.data?.id) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    throw new AdminCatalogApiError(
      firstError?.trim() ||
        payload.message?.trim() ||
        "Unable to update product.",
      response.status,
    );
  }

  return mapAdminApiProductToProduct(payload.data);
}

export type AdminUploadedImage = {
  id: string;
  path: string;
  url: string;
  is_primary?: boolean;
};

export async function uploadAdminProductImage(
  catalogProductId: string,
  file: File,
): Promise<AdminUploadedImage> {
  const formData = new FormData();
  formData.append("image", file, file.name);

  const response = await fetch(
    `/api/admin/products/${encodeURIComponent(catalogProductId)}/images`,
    {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: AdminUploadedImage;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false || !payload.data?.id) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    throw new AdminCatalogApiError(
      firstError?.trim() || payload.message?.trim() || "Unable to upload product image.",
      response.status,
    );
  }

  return payload.data;
}

export async function deleteAdminProductImage(catalogImageId: string): Promise<void> {
  const response = await fetch(
    `/api/admin/product-images/${encodeURIComponent(catalogImageId)}`,
    {
      method: "DELETE",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{ success?: boolean; message?: string }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to delete product image.",
      response.status,
    );
  }
}

export async function setAdminProductImagePrimary(catalogImageId: string): Promise<void> {
  const response = await fetch(
    `/api/admin/product-images/${encodeURIComponent(catalogImageId)}/primary`,
    {
      method: "PATCH",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{ success?: boolean; message?: string }>(response);

  if (!response.ok || payload.success === false) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to set primary product image.",
      response.status,
    );
  }
}

/**
 * Uploads any pending local files for a product, then sets the primary image.
 * Used after create (and as a safety net on update).
 */
export async function persistProductImages(options: {
  catalogProductId: string;
  images: import("@/lib/types/catalog").ProductImage[];
  thumbnailImageId: number | null;
  pendingFiles: Map<number, File>;
}): Promise<import("@/lib/types/catalog").ProductImage[]> {
  const { catalogProductId, images, thumbnailImageId, pendingFiles } = options;
  const nextImages: import("@/lib/types/catalog").ProductImage[] = [];

  for (const image of images) {
    if (image.catalogImageId) {
      nextImages.push(image);
      continue;
    }

    const file = pendingFiles.get(image.id);
    if (!file) {
      continue;
    }

    const uploaded = await uploadAdminProductImage(catalogProductId, file);
    nextImages.push({
      ...image,
      catalogImageId: uploaded.id,
      url: uploaded.url,
      path: uploaded.path,
    });
  }

  const preferred =
    nextImages.find((image) => image.id === thumbnailImageId) ?? nextImages[0];

  if (preferred?.catalogImageId) {
    await setAdminProductImagePrimary(preferred.catalogImageId);
  }

  return nextImages;
}

export async function fetchProductFormSchema(
  categoryId: string,
): Promise<import("@/lib/types/catalog").ProductFormSchema> {
  const response = await fetch(
    `/api/admin/categories/${encodeURIComponent(categoryId)}/product-form-schema`,
    {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: import("@/lib/types/catalog").ProductFormSchema;
  }>(response);

  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminCatalogApiError(
      payload.message?.trim() || "Unable to load product form schema.",
      response.status,
    );
  }

  return payload.data;
}

export async function generateAdminConfigurations(input: {
  /** Configuration Template id (legacy `product_types` / ProductType). */
  productTypeId: string;
  selectedValues: Record<string, string[]>;
  baseSku: string;
  defaultPrice?: number | null;
  valueLabels?: Record<string, string>;
}): Promise<import("@/lib/types/catalog").ProductConfigurationDraft[]> {
  const response = await fetch(
    `/api/admin/product-types/${encodeURIComponent(input.productTypeId)}/generate-configurations`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        selected_values: input.selectedValues,
        base_sku: input.baseSku,
        default_price: input.defaultPrice ?? null,
      }),
      cache: "no-store",
    },
  );

  const payload = await parseJsonResponse<{
    success?: boolean;
    message?: string;
    data?: {
      configurations?: Array<{
        attribute_value_ids: string[];
        selections?: Record<string, string>;
        sku?: string | null;
        stock_quantity?: number;
        price?: number | null;
        barcode?: string | null;
      }>;
    };
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    throw new AdminCatalogApiError(
      firstError?.trim() ||
        payload.message?.trim() ||
        "Unable to generate configurations.",
      response.status,
    );
  }

  const labels = input.valueLabels ?? {};

  return (payload.data?.configurations ?? []).map((row) => {
    const label =
      row.attribute_value_ids.map((id) => labels[id]).filter(Boolean).join(" / ") ||
      row.sku ||
      "Configuration";

    return {
      attributeValueIds: row.attribute_value_ids,
      label,
      sku: row.sku?.trim() || "",
      stock: Math.max(0, Math.floor(row.stock_quantity ?? 0)),
      price: row.price ?? null,
      barcode: row.barcode?.trim() || "",
    };
  });
}


