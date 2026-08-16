import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useState } from 'react';
import { StatusBar } from 'expo-status-bar';

import { setUnauthorizedHandler } from '@/src/core/api';
import { bootstrapSession, useAdminAuthStore } from '@/src/core/auth';
import {
  configureForegroundNotificationHandler,
  useAdminPushBootstrap,
} from '@/src/features/notifications';
import { colors } from '@/src/shared/theme/colors';

SplashScreen.preventAutoHideAsync().catch(() => undefined);

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
    },
  },
});

let foregroundHandlerConfigured = false;

function AdminPushBootstrapGate() {
  useAdminPushBootstrap();
  return null;
}

export default function RootLayout() {
  const bootstrapStatus = useAdminAuthStore((s) => s.bootstrapStatus);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (!foregroundHandlerConfigured) {
      configureForegroundNotificationHandler();
      foregroundHandlerConfigured = true;
    }
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(async () => {
      useAdminAuthStore.getState().setUnauthenticated();
      queryClient.clear();
    });

    bootstrapSession()
      .catch(() => {
        useAdminAuthStore.getState().setUnauthenticated();
      })
      .finally(() => {
        setReady(true);
        SplashScreen.hideAsync().catch(() => undefined);
      });
  }, []);

  if (!ready || bootstrapStatus === 'bootstrapping') {
    return null;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <StatusBar style="light" />
      <AdminPushBootstrapGate />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.background } }} />
    </QueryClientProvider>
  );
}
