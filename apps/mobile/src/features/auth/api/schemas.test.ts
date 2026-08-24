import { loginRequestSchema, registerRequestSchema } from './schemas';

describe('loginRequestSchema', () => {
  it('accepts the same login payload keys used by POST /login', () => {
    const parsed = loginRequestSchema.parse({
      email: 'ada@example.com',
      password: 'Password123!',
    });

    expect(parsed).toEqual({
      email: 'ada@example.com',
      password: 'Password123!',
    });
    expect(Object.keys(parsed).sort()).toEqual(['email', 'password']);
  });

  it('keeps login validation messages unchanged', () => {
    const invalid = loginRequestSchema.safeParse({
      email: 'not-an-email',
      password: '',
    });

    expect(invalid.success).toBe(false);
    if (invalid.success) return;

    const byPath = Object.fromEntries(
      invalid.error.issues.map((issue) => [String(issue.path[0]), issue.message]),
    );
    expect(byPath.email).toBe('Enter a valid email');
    expect(byPath.password).toBe('Password is required');
  });
});

describe('registerRequestSchema', () => {
  it('accepts the same register payload keys used by POST /register', () => {
    const parsed = registerRequestSchema.parse({
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      phone: '0712345678',
      password: 'Password123!',
      password_confirmation: 'Password123!',
    });

    expect(parsed).toEqual({
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      phone: '0712345678',
      password: 'Password123!',
      password_confirmation: 'Password123!',
    });
  });

  it('keeps phone optional', () => {
    const parsed = registerRequestSchema.parse({
      name: 'Ada',
      email: 'ada@example.com',
      password: 'Password123!',
      password_confirmation: 'Password123!',
    });

    expect(parsed.phone).toBeUndefined();
  });

  it('keeps register validation messages unchanged', () => {
    const empty = registerRequestSchema.safeParse({
      name: '',
      email: 'bad',
      password: '',
      password_confirmation: '',
    });

    expect(empty.success).toBe(false);
    if (empty.success) return;

    const byPath = Object.fromEntries(
      empty.error.issues.map((issue) => [String(issue.path[0]), issue.message]),
    );
    expect(byPath.name).toBe('Name is required');
    expect(byPath.email).toBe('Enter a valid email');
    expect(byPath.password).toBe('Password must be at least 8 characters');
    expect(byPath.password_confirmation).toBe('Confirm your password');
  });

  it('requires matching confirmation without changing the rule', () => {
    const mismatch = registerRequestSchema.safeParse({
      name: 'Ada',
      email: 'ada@example.com',
      password: 'Password123!',
      password_confirmation: 'Different1',
    });

    expect(mismatch.success).toBe(false);
    if (mismatch.success) return;
    expect(mismatch.error.issues[0]?.message).toBe('Passwords do not match');
    expect(mismatch.error.issues[0]?.path).toEqual(['password_confirmation']);
  });
});
