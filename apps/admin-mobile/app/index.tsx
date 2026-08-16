import { Redirect } from 'expo-router';

import { useAdminAuthStore } from '@/src/core/auth';

export default function IndexScreen() {
  const status = useAdminAuthStore((s) => s.status);

  if (status === 'authenticated') {
    return <Redirect href="/(app)/(tabs)/dashboard" />;
  }

  return <Redirect href="/(auth)/login" />;
}
