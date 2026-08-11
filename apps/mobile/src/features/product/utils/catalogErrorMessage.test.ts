import { ApiError } from '@/src/core/errors';
import { getCatalogErrorMessage } from './catalogErrorMessage';

describe('getCatalogErrorMessage', () => {
  it('maps Contract v1 catalog errors', () => {
    expect(
      getCatalogErrorMessage(
        new ApiError({ message: 'Maint', status: 503, code: 'maintenance_mode' }),
      ),
    ).toBe('Maint');

    expect(
      getCatalogErrorMessage(
        new ApiError({ message: '', status: 404, code: 'not_found' }),
      ),
    ).toMatch(/not found/i);

    expect(
      getCatalogErrorMessage(
        new ApiError({ message: '', status: 500, code: 'server_error' }),
      ),
    ).toMatch(/Something went wrong\. Please try again\./i);

    expect(
      getCatalogErrorMessage(
        new ApiError({ message: '', status: 401, code: 'unauthenticated' }),
      ),
    ).toMatch(/sign in/i);
  });
});
