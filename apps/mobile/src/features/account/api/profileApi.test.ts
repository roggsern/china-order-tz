import { mapCustomerProfile } from './profileApi';

describe('mapCustomerProfile phone', () => {
  it('maps GET /profile phone as a nullable string', () => {
    expect(
      mapCustomerProfile({
        first_name: 'Ada',
        last_name: 'Lovelace',
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        phone: '+255712345678',
      })?.phone,
    ).toBe('+255712345678');
  });

  it('treats missing, null, and blank profile phones as null', () => {
    expect(mapCustomerProfile({ email: 'ada@example.com', phone: null })?.phone).toBeNull();
    expect(mapCustomerProfile({ email: 'ada@example.com', phone: '' })?.phone).toBeNull();
    expect(mapCustomerProfile({ email: 'ada@example.com', phone: '   ' })?.phone).toBeNull();
    expect(mapCustomerProfile({ email: 'ada@example.com' })?.phone).toBeNull();
  });
});
