/**
 * Only allow safe in-app relative return paths after login/register.
 * Rejects external URLs, protocol-relative, and path traversal.
 */
export function sanitizeAuthReturnTo(value: unknown): string | null {
  if (typeof value !== 'string' || value.trim() === '') {
    return null;
  }

  let decoded = value.trim();
  // Decode repeatedly to catch double-encoded traversal / redirects.
  for (let i = 0; i < 3; i += 1) {
    try {
      const next = decodeURIComponent(decoded);
      if (next === decoded) break;
      decoded = next;
    } catch {
      break;
    }
  }

  decoded = decoded.trim();
  if (!decoded.startsWith('/(app)/')) {
    return null;
  }
  if (decoded.includes('://')) {
    return null;
  }
  // Protocol-relative or empty authority after scheme strip.
  if (decoded.includes('//')) {
    return null;
  }

  const pathOnly = decoded.split('?')[0] ?? decoded;
  const segments = pathOnly.split('/');
  for (const segment of segments) {
    if (segment === '..' || segment === '.') {
      return null;
    }
    // Encoded dots that survived partial decode attempts.
    if (/%2e/i.test(segment)) {
      return null;
    }
  }

  return decoded;
}

export function buildLoginHref(returnTo?: string | null): string {
  if (!returnTo) {
    return '/(auth)/login';
  }
  const safe = sanitizeAuthReturnTo(returnTo);
  if (!safe) {
    return '/(auth)/login';
  }
  return `/(auth)/login?returnTo=${encodeURIComponent(safe)}`;
}

export function buildRegisterHref(returnTo?: string | null): string {
  if (!returnTo) {
    return '/(auth)/register';
  }
  const safe = sanitizeAuthReturnTo(returnTo);
  if (!safe) {
    return '/(auth)/register';
  }
  return `/(auth)/register?returnTo=${encodeURIComponent(safe)}`;
}
