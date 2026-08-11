import { z } from 'zod';

/** Loose object — Contract v1 resources evolve; mapping extracts known fields. */
export const orderListCardSchema = z.record(z.string(), z.unknown());

export const ordersListMetaSchema = z.object({
  current_page: z.number().int().positive().optional(),
  last_page: z.number().int().positive().optional(),
  per_page: z.number().int().positive().optional(),
  total: z.number().int().nonnegative().optional(),
});

export const ordersListLinksSchema = z
  .object({
    first: z.string().nullable().optional(),
    last: z.string().nullable().optional(),
    prev: z.string().nullable().optional(),
    next: z.string().nullable().optional(),
  })
  .passthrough();

export const orderDetailSchema = z.record(z.string(), z.unknown());

export const orderTrackingSchema = z.record(z.string(), z.unknown());
