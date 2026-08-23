import { apiClient } from '@/src/core/api';
import { applyCheckoutShippingChoice } from './checkoutApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    post: jest.fn(),
  },
}));

const mockPost = apiClient.post as jest.MockedFunction<typeof apiClient.post>;

describe('applyCheckoutShippingChoice', () => {
  beforeEach(() => {
    mockPost.mockReset();
  });

  it('posts the selected option through POST /checkout/{id}/shipping-choice', async () => {
    mockPost.mockResolvedValue({
      data: {
        id: 'sess-1',
        status: 'validated',
        shipping_choice: 'company_shipping',
        shipping_method: 'air',
        shipping_total: '12000',
        grand_total: '92000',
        shipping_ready: true,
        is_expired: false,
      },
    } as never);

    const session = await applyCheckoutShippingChoice('sess-1', {
      shippingChoice: 'company_shipping',
      shippingMethod: 'air',
    });

    expect(mockPost).toHaveBeenCalledWith('/checkout/sess-1/shipping-choice', {
      shipping_choice: 'company_shipping',
      shipping_method: 'air',
      agent_name: null,
      agent_contact: null,
    });
    expect(session.shippingTotal).toBe('12000');
    expect(session.grandTotal).toBe('92000');
  });
});
