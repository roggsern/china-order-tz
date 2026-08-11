import { z } from 'zod';

const entityRefSchema = z
  .object({
    id: z.union([z.string(), z.number()]).optional(),
    slug: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
  })
  .passthrough()
  .nullable()
  .optional();

const mediaSchema = z
  .object({
    id: z.string().optional(),
    url: z.string().nullable().optional(),
    path: z.string().nullable().optional(),
    alt_text: z.string().nullable().optional(),
  })
  .passthrough()
  .nullable()
  .optional();

/** Product hit from suggest / products — flexible over CustomerProductCardResource + search extras. */
export const searchHitSchema = z
  .object({
    id: z.union([z.string(), z.number()]),
    slug: z.string().optional(),
    name: z.string(),
    price: z.union([z.string(), z.number()]).nullable().optional(),
    primary_image: mediaSchema,
    marketplace: z.string().optional(),
    commerce_channel_code: z.string().nullable().optional(),
    availability_status: z.string().nullable().optional(),
    brand: entityRefSchema,
    store: entityRefSchema,
    relevance_score: z.number().nullable().optional(),
    matched_on: z.array(z.string()).optional(),
  })
  .passthrough();

export const searchEntitySuggestionSchema = z
  .object({
    kind: z.string().optional(),
    id: z.union([z.string(), z.number()]),
    slug: z.string().nullable().optional(),
    name: z.string(),
    relevance_score: z.number().nullable().optional(),
  })
  .passthrough();

export const searchSuggestDataSchema = z
  .object({
    q: z.string().optional().default(''),
    scope: z.string().optional().default('all'),
    products: z.array(z.unknown()).optional().default([]),
    brands: z.array(z.unknown()).optional().default([]),
    stores: z.array(z.unknown()).optional().default([]),
    categories: z.array(z.unknown()).optional().default([]),
  })
  .passthrough();

export const searchProductsMetaSchema = z
  .object({
    current_page: z.coerce.number().optional().default(1),
    last_page: z.coerce.number().optional().default(1),
    per_page: z.coerce.number().optional().default(24),
    total: z.coerce.number().optional().default(0),
    q: z.string().optional().default(''),
    scope: z.string().optional().default('all'),
  })
  .passthrough();
