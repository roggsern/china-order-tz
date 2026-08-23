import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';
import {
  fetchDeliveryOption,
  selectDeliveryOption,
  updateDeliveryOption,
} from './deliveryOptionApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    get: jest.fn(),
    post: jest.fn(),
    patch: jest.fn(),
  },
}));

const mockGet = apiClient.get as jest.MockedFunction<typeof apiClient.get>;
const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;
const mockPatch = apiClient.patch as jest.MockedFunction<typeof apiClient.patch>;

describe('deliveryOptionApi', () => {
  beforeEach(() => {
    mockGet.mockReset();
    mockPost.mockReset();
    mockPatch.mockReset();
  });

  it('GETs post-pay delivery-option state from the order endpoint', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        delivery_option: {
          id: 'do-1',
          delivery_type: 'company_shipping',
          delivery_status: 'pending',
        },
        available: {
          market: 'china',
          delivery_types: [{ value: 'company_shipping', label: 'Company shipping' }],
          shipping_methods: [],
        },
      },
    } as never);

    const show = await fetchDeliveryOption('ord-1');
    expect(mockGet).toHaveBeenCalledWith('/orders/ord-1/delivery-option');
    expect(show.deliveryOption?.deliveryType).toBe('company_shipping');
  });

  it('surfaces backend validation errors without inventing success', async () => {
    mockPost.mockRejectedValue(
      new ApiError({
        message: 'Delivery option already selected. Use PATCH to update agent details.',
        status: 422,
        code: 'validation_failed',
        errors: {
          order: ['Delivery option already selected. Use PATCH to update agent details.'],
        },
      }),
    );

    await expect(
      selectDeliveryOption({
        orderId: 'ord-1',
        deliveryType: 'company_shipping',
        shippingMethod: 'air',
      }),
    ).rejects.toMatchObject({ code: 'validation_failed' });
  });

  it('PATCHes confirm using the backend delivery_status value', async () => {
    mockPatch.mockResolvedValue({
      success: true,
      data: {
        id: 'do-1',
        delivery_type: 'company_shipping',
        delivery_status: 'confirmed',
      },
    } as never);

    const option = await updateDeliveryOption({
      orderId: 'ord-1',
      deliveryStatus: 'confirmed',
    });
    expect(mockPatch).toHaveBeenCalledWith('/orders/ord-1/delivery-option', {
      delivery_status: 'confirmed',
    });
    expect(option?.deliveryStatus).toBe('confirmed');
  });
});
