export type ReturnRequestStatus =
  | 'requested'
  | 'approved'
  | 'rejected'
  | 'inspection'
  | 'completed'
  | 'cancelled'
  | (string & {});

export type ReturnItem = {
  id: string;
  returnRequestId: string | null;
  orderItemId: string;
  quantity: number;
  reason: string | null;
  condition: string | null;
  resolution: string | null;
  refundAmount: string | number | null;
  replacementRequested: boolean;
  productName: string | null;
  orderedQuantity: number | null;
};

export type ReturnRefund = {
  id: string;
  amount: string | number | null;
  currency: string | null;
  status: string | null;
  statusLabel: string | null;
  method: string | null;
  reference: string | null;
  createdAt: string | null;
};

export type CustomerReturnRequest = {
  id: string;
  orderId: string | null;
  orderNumber: string | null;
  orderStatus: string | null;
  status: string | null;
  reason: string | null;
  description: string | null;
  customerNotes: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  items: ReturnItem[];
  refunds: ReturnRefund[];
};

export type CustomerReturnsPage = {
  returns: CustomerReturnRequest[];
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
};

export type CreateReturnItemInput = {
  orderItemId: string;
  quantity: number;
  reason?: string | null;
};

export type CreateReturnInput = {
  orderId: string;
  reason: string;
  description?: string | null;
  customerNotes?: string | null;
  items: CreateReturnItemInput[];
};

/** Web CustomerReturnRequestContent reason options — backend stores free-text. */
export const RETURN_REASON_OPTIONS = [
  'Damaged on arrival',
  'Wrong item received',
  'Not as described',
  'Changed mind',
  'Other',
] as const;
