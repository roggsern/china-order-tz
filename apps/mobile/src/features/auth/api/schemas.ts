import { z } from 'zod';
import { userSchema } from '@/src/shared/types/user';

/** Login / register success — token is top-level (Contract v1). */
export const authSessionResponseSchema = z.object({
  success: z.literal(true),
  message: z.string().optional(),
  token: z.string().min(1),
  token_type: z.string().optional(),
  data: userSchema,
});

export type AuthSessionResponse = z.infer<typeof authSessionResponseSchema>;

export const loginRequestSchema = z.object({
  email: z.string().email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
});

export type LoginRequest = z.infer<typeof loginRequestSchema>;

export const registerRequestSchema = z
  .object({
    name: z.string().min(1, 'Name is required'),
    email: z.string().email('Enter a valid email'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string().min(1, 'Confirm your password'),
    phone: z.string().optional(),
  })
  .refine((value) => value.password === value.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

export type RegisterRequest = z.infer<typeof registerRequestSchema>;
