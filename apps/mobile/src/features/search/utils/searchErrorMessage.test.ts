import { ApiError } from '@/src/core/errors';
import { getSearchErrorMessage } from './searchErrorMessage';

describe('getSearchErrorMessage', () => {
  it('maps Contract v1 search error codes', () => {
    expect(
      getSearchErrorMessage(
        new ApiError({ message: 'Maint', status: 503, code: 'maintenance_mode' }),
      ),
    ).toBe('Maint');

    expect(
      getSearchErrorMessage(
        new ApiError({ message: '', status: 422, code: 'validation_failed' }),
      ),
    ).toMatch(/check your search/i);

    expect(
      getSearchErrorMessage(
        new ApiError({ message: '', status: 401, code: 'unauthenticated' }),
      ),
    ).toMatch(/sign in/i);

    expect(
      getSearchErrorMessage(
        new ApiError({ message: '', status: 500, code: 'server_error' }),
      ),
    ).toMatch(/Something went wrong\. Please try again\./i);
  });
});
