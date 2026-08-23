import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';
import { selectReceivingMethod } from './ordersApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    post: jest.fn(),
  },
}));

const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;

describe('selectReceivingMethod', () => {
  beforeEach(() => {
    mockPost.mockReset();
  });

  it('POSTs receiving_method to the order endpoint', async () => {
    mockPost.mockResolvedValue({
      data: {
        receiving_choice: {
          eligible: true,
          can_select: false,
          selected_method: 'self_pickup',
          selected_method_label: 'Self Pickup',
          selected_at: '2026-08-24T00:00:00Z',
        },
      },
    } as never);

    const snapshot = await selectReceivingMethod({
      orderId: 'ord-1',
      receivingMethod: 'self_pickup',
    });

    expect(mockPost).toHaveBeenCalledWith('/orders/ord-1/receiving-method', {
      receiving_method: 'self_pickup',
    });
    expect(snapshot).toMatchObject({
      selectedMethod: 'self_pickup',
      canSelect: false,
    });
  });

  it('posts negotiated_delivery as the backend-supported delivery value', async () => {
    mockPost.mockResolvedValue({
      data: {
        receiving_choice: {
          eligible: true,
          can_select: false,
          selected_method: 'negotiated_delivery',
          selected_method_label: 'Negotiated Delivery',
          selected_at: '2026-08-24T00:00:00Z',
        },
      },
    } as never);

    const snapshot = await selectReceivingMethod({
      orderId: 'ord-1',
      receivingMethod: 'negotiated_delivery',
    });

    expect(mockPost).toHaveBeenCalledWith('/orders/ord-1/receiving-method', {
      receiving_method: 'negotiated_delivery',
    });
    expect(snapshot?.selectedMethod).toBe('negotiated_delivery');
  });

  it('surfaces backend validation errors', async () => {
    mockPost.mockRejectedValue(
      new ApiError({
        message: 'Receiving method has already been selected.',
        status: 422,
        code: 'validation_failed',
        errors: {
          receiving_method: ['Receiving method has already been selected.'],
        },
      }),
    );

    await expect(
      selectReceivingMethod({
        orderId: 'ord-1',
        receivingMethod: 'negotiated_delivery',
      }),
    ).rejects.toMatchObject({
      code: 'validation_failed',
    });
  });
});
