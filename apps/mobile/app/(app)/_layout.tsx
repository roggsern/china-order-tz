import { Redirect, Stack, useGlobalSearchParams, usePathname } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { useCart } from '@/src/features/cart/hooks/useCart';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { AppHeader } from '@/src/shared/ui/AppHeader';

function StackAppHeader({ title }: { title: string }) {
  const cartQuery = useCart();
  return (
    <AppHeader
      title={title}
      showBrand={false}
      showBack
      showSearch
      showCart
      cartCount={cartQuery.data?.itemCount ?? 0}
    />
  );
}

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
      <Stack.Screen
        name="product/[id]"
        options={{
          header: () => <StackAppHeader title="Product" />,
        }}
      />
      {/* Default headers for checkout/payment — no payment UX changes. */}
      <Stack.Screen name="checkout" options={{ title: 'Checkout' }} />
      <Stack.Screen name="payment" options={{ title: 'Payment' }} />
      <Stack.Screen
        name="orders/[id]/index"
        options={{
          header: () => <StackAppHeader title="Order" />,
        }}
      />
      <Stack.Screen
        name="orders/[id]/tracking"
        options={{
          header: () => <StackAppHeader title="Tracking" />,
        }}
      />
      <Stack.Screen
        name="account/addresses"
        options={{
          header: () => <StackAppHeader title="Addresses" />,
        }}
      />
      <Stack.Screen
        name="account/address-form"
        options={{
          header: () => <StackAppHeader title="Address" />,
        }}
      />
      <Stack.Screen
        name="account/wishlist"
        options={{
          header: () => <StackAppHeader title="Wishlist" />,
        }}
      />
      <Stack.Screen
        name="account/profile"
        options={{
          header: () => <StackAppHeader title="Profile" />,
        }}
      />
      <Stack.Screen
        name="account/change-password"
        options={{
          header: () => <StackAppHeader title="Password" />,
        }}
      />
      <Stack.Screen
        name="account/notifications"
        options={{
          header: () => <StackAppHeader title="Notifications" />,
        }}
      />
      <Stack.Screen
        name="account/support/index"
        options={{
          header: () => <StackAppHeader title="Support" />,
        }}
      />
      <Stack.Screen
        name="account/support/[id]"
        options={{
          header: () => <StackAppHeader title="Ticket" />,
        }}
      />
      <Stack.Screen
        name="account/support-new"
        options={{
          header: () => <StackAppHeader title="New ticket" />,
        }}
      />
    </Stack>
  );
}
