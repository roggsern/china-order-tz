import { z } from 'zod';
import { HOMEPAGE_COMMERCE_CONTEXTS } from '../models/types';

export const homepageCommerceContextSchema = z.enum(HOMEPAGE_COMMERCE_CONTEXTS);

const mediaSchema = z
  .object({
    id: z.string().optional(),
    url: z.string().nullable().optional(),
    alt_text: z.string().nullable().optional(),
  })
  .passthrough();

const ctaSchema = z
  .object({
    type: z.string().nullable().optional(),
    label: z.string().nullable().optional(),
    value: z.string().nullable().optional(),
    url: z.string().nullable().optional(),
  })
  .passthrough()
  .nullable()
  .optional();

const heroSlideSchema = z
  .object({
    id: z.string(),
    headline: z.string().nullable().optional().default(null),
    subheadline: z.string().nullable().optional().default(null),
    eyebrow_text: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    desktop_media: mediaSchema.nullable().optional(),
    mobile_media: mediaSchema.nullable().optional(),
    primary_cta: ctaSchema,
    secondary_cta: ctaSchema,
    position: z.coerce.number().default(0),
  })
  .passthrough();

const featuredItemSchema = z
  .object({
    item_type: z.string(),
    id: z.string(),
    data: z.record(z.string(), z.unknown()).default({}),
  })
  .passthrough();

const featuredContentSchema = z
  .object({
    id: z.string(),
    cms_homepage_section_id: z.string().optional(),
    title: z.string().nullable().optional().default(null),
    subtitle: z.string().nullable().optional().default(null),
    source_type: z.string().nullable().optional(),
    limit: z.coerce.number().optional(),
    position: z.coerce.number().default(0),
    items: z.array(featuredItemSchema).optional().default([]),
  })
  .passthrough();

const sectionSchema = z
  .object({
    id: z.string(),
    cms_homepage_layout_id: z.string().optional(),
    section_type: z.string(),
    title: z.string().nullable().optional().default(null),
    subtitle: z.string().nullable().optional().default(null),
    position: z.coerce.number().default(0),
    is_visible: z.boolean().default(true),
    configuration: z.record(z.string(), z.unknown()).optional(),
    hero_slides: z.array(heroSlideSchema).optional(),
    featured_contents: z.array(featuredContentSchema).optional(),
  })
  .passthrough();

const layoutSchema = z
  .object({
    id: z.string(),
    name: z.string(),
    slug: z.string(),
    commerce_context: z.string(),
    status: z.string(),
    is_default: z.boolean().default(false),
    sections: z.array(sectionSchema).default([]),
  })
  .passthrough();

const campaignSchema = z
  .object({
    id: z.string(),
    name: z.string(),
    slug: z.string(),
    priority: z.coerce.number().default(0),
    promotion_ids: z.array(z.string()).optional(),
  })
  .passthrough();

const metaSchema = z
  .object({
    commerce_context: z.string(),
    resolved_commerce_context: z.string().optional(),
    allow_global_fallback: z.boolean().optional(),
    used_global_fallback: z.boolean().optional(),
    campaign: campaignSchema.nullable().optional(),
    message: z.string().optional(),
  })
  .passthrough();

/** Contract v1 success envelope for homepage. */
export const homepageResponseSchema = z.object({
  success: z.literal(true),
  data: layoutSchema.nullable(),
  meta: metaSchema,
  message: z.string().optional(),
  code: z.string().optional(),
  errors: z.record(z.string(), z.array(z.string())).optional(),
});

export type HomepageResponseParsed = z.infer<typeof homepageResponseSchema>;
