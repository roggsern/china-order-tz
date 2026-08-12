import { QueryClientProvider } from '@tanstack/react-query';
import * as Linking from 'expo-linking';
import { Stack, router } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useState } from 'react';
import 'react-native-reanimated';

import { createAppQueryClient } from '@/src/core/api/queryClient';
import { registerAppQueryClient } from '@/src/core/api/queryClientRegistry';
import { SplashView } from '@/src/features/auth';
import { usePushNotificationsBootstrap } from '@/src/features/notifications';
import { AppErrorBoundary } from '@/src/shared/components/AppErrorBoundary';
import { useAuthBootstrap } from '@/src/shared/hooks/useAuthBootstrap';

export { ErrorBoundary } from 'expo-router';

SplashScreen.preventAutoHideAsync().catch(() => {
  // Ignore if already prevented.
});

function isPaymentReturnUrl(url: string): boolean {
  return /payment-return/i.test(url);
}

function AuthenticatedPushBootstrap() {
  usePushNotificationsBootstrap();
  return null;
}

export default function RootLayout() {
  const [queryClient] = useState(() => {
    const client = createAppQueryClient();
    registerAppQueryClient(client);
    return client;
  });
  const bootstrapStatus = useAuthBootstrap();

  useEffect(() => {
    if (bootstrapStatus === 'complete') {
      SplashScreen.hideAsync().catch(() => undefined);
    }
  }, [bootstrapStatus]);

  // Warm-start: OS delivers payment-return while app is already running.
  useEffect(() => {
    if (bootstrapStatus !== 'complete') return;

    const subscription = Linking.addEventListener('url', ({ url }) => {
      if (!isPaymentReturnUrl(url)) return;
      try {
        const parsed = Linking.parse(url);
        const path = parsed.path?.replace(/^\//, '') || 'payment-return';
        const query = parsed.queryParams
          ? `?${new URLSearchParams(
              Object.entries(parsed.queryParams).reduce<Record<string, string>>(
                (acc, [key, value]) => {
                  if (typeof value === 'string') acc[key] = value;
                  else if (Array.isArray(value) && typeof value[0] === 'string') {
                    acc[key] = value[0];
                  }
                  return acc;
                },
                {},
              ),
            ).toString()}`
          : '';
        router.push(`/${path}${query}` as never);
      } catch {
        router.push('/payment-return' as never);
      }
    });

    return () => subscription.remove();
  }, [bootstrapStatus]);

  if (bootstrapStatus !== 'complete') {
    return <SplashView message="Preparing CHINA ORDER TZ…" />;
  }

  return (
    <AppErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <AuthenticatedPushBootstrap />
        <Stack screenOptions={{ headerShown: true }}>
          <Stack.Screen name="index" options={{ headerShown: false }} />
          <Stack.Screen name="(auth)" options={{ headerShown: false }} />
          <Stack.Screen name="(app)" options={{ headerShown: false }} />
          <Stack.Screen
            name="payment-return"
            options={{ title: 'Payment return', headerShown: false }}
          />
          <Stack.Screen name="+not-found" />
        </Stack>
      </QueryClientProvider>
    </AppErrorBoundary>
  );
}
