import { z } from 'zod';

export const deliveryAddressInputSchema = z.object({
  recipientName: z.string().trim().min(1, 'Recipient name is required'),
  phone: z.string().trim().min(1, 'Phone is required'),
  country: z.string().trim().min(1, 'Country is required'),
  region: z.string().trim().min(1, 'Region is required'),
  city: z.string().trim().min(1, 'City is required'),
  district: z.string().trim().min(1, 'District is required'),
  street: z.string().trim().min(1, 'Street is required'),
  landmark: z.string().trim().optional().nullable(),
  postalCode: z.string().trim().optional().nullable(),
});

export const shippingChoiceValueSchema = z.enum([
  'company_shipping',
  'customer_agent',
  'self_pickup',
  'negotiated_delivery',
]);
