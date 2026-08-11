import { Redirect, Stack, useGlobalSearchParams, usePathname } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';

/**
 * Rebuild an in-app returnTo from the current private route + query.
 * Preserves payment continuation params across auth redirects.
 */
function buildAppReturnTo(
  pathname: string,
  params: Record<string, string | string[] | undefined>,
): string | null {
  const normalized = pathname.startsWith('/(app)')
    ? pathname
    : pathname.startsWith('/')
      ? `/(app)${pathname}`
      : `/(app)/${pathname}`;

  if (!normalized.startsWith('/(app)/')) {
    return null;
  }

  const query: string[] = [];
  for (const [key, value] of Object.entries(params)) {
    if (key === 'returnTo') continue;
    const raw = Array.isArray(value) ? value[0] : value;
    if (typeof raw !== 'string' || raw.trim() === '') continue;
    query.push(
      `${encodeURIComponent(key)}=${encodeURIComponent(raw.trim())}`,
    );
  }

  return query.length > 0 ? `${normalized}?${query.join('&')}` : normalized;
}

/**
 * Private commerce shell — requires authenticated session.
 */
export default function AppLayout() {
  const status = useAuthStore((s) => s.status);
  const pathname = usePathname();
  const params = useGlobalSearchParams();

  if (status !== 'authenticated') {
    const returnTo = buildAppReturnTo(
      pathname,
      params as Record<string, string | string[] | undefined>,
    );
    return <Redirect href={buildLoginHref(returnTo) as never} />;
  }

  return (
    <Stack>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="product/[id]" options={{ title: 'Product' }} />
      <Stack.Screen name="checkout" options={{ title: 'Checkout' }} />
      <Stack.Screen name="payment" options={{ title: 'Payment' }} />
      <Stack.Screen name="orders/[id]/index" options={{ title: 'Order' }} />
      <Stack.Screen
        name="orders/[id]/tracking"
        options={{ title: 'Tracking' }}
      />
    </Stack>
  );
}
