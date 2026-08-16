import { Redirect, Stack } from 'expo-router';

import { useAdminAuthStore } from '@/src/core/auth';

export default function AuthLayout() {
  const status = useAdminAuthStore((s) => s.status);

  if (status === 'authenticated') {
    return <Redirect href="/(app)/(tabs)/dashboard" />;
  }

  return <Stack screenOptions={{ headerShown: false }} />;
}
