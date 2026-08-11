import { Redirect } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';

/**
 * Bootstrap redirect — splash/auth decision already completed in root layout.
 */
export default function Index() {
  const status = useAuthStore((s) => s.status);

  if (status === 'authenticated') {
    return <Redirect href="/(app)/(tabs)/home" />;
  }

  return <Redirect href="/(auth)/login" />;
}
