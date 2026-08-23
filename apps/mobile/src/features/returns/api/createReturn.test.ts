import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';
import { createCustomerReturn } from './returnsApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    post: jest.fn(),
  },
}));

const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;

describe('createCustomerReturn', () => {
  beforeEach(() => {
    mockPost.mockReset();
  });

  it('POSTs the backend-supported return payload', async () => {
    mockPost.mockResolvedValue({
      success: true,
      data: {
        id: 'ret-1',
        order_id: 'ord-1',
        status: 'requested',
        reason: 'Damaged on arrival',
        items: [
          {
            id: 'ri-1',
            order_item_id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            quantity: 1,
          },
        ],
        refunds: [],
      },
    } as never);

    const created = await createCustomerReturn({
      orderId: 'ord-1',
      reason: 'Damaged on arrival',
      items: [
        {
          orderItemId: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
          quantity: 1,
        },
      ],
    });

    expect(mockPost).toHaveBeenCalledWith('/orders/ord-1/returns', {
      reason: 'Damaged on arrival',
      description: null,
      customer_notes: null,
      items: [
        {
          order_item_id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
          quantity: 1,
        },
      ],
    });
    expect(created.status).toBe('requested');
    expect(created.refunds).toEqual([]);
  });

  it('does not fake success on a backend validation error', async () => {
    mockPost.mockRejectedValue(
      new ApiError({
        message: 'This order already has an open return request.',
        status: 422,
        code: 'validation_failed',
        errors: {
          order: ['This order already has an open return request.'],
        },
      }),
    );

    await expect(
      createCustomerReturn({
        orderId: 'ord-1',
        reason: 'Changed mind',
        items: [
          {
            orderItemId: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            quantity: 1,
          },
        ],
      }),
    ).rejects.toMatchObject({
      code: 'validation_failed',
      message: 'This order already has an open return request.',
    });
    expect(mockPost).toHaveBeenCalledTimes(1);
  });
});
