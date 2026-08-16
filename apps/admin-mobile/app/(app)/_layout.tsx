import { Redirect, Stack } from 'expo-router';

import { useAdminAuthStore } from '@/src/core/auth';

export default function AppLayout() {
  const status = useAdminAuthStore((s) => s.status);

  if (status !== 'authenticated') {
    return <Redirect href="/(auth)/login" />;
  }

  return <Stack screenOptions={{ headerShown: false }} />;
}
