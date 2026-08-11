import { z } from 'zod';

export const userSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string().email(),
  phone: z.string().nullable().optional(),
  is_active: z.boolean().optional(),
  email_verified_at: z.string().nullable().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
});

export type User = z.infer<typeof userSchema>;
