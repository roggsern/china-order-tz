import { Redirect, Stack } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';

export default function AuthLayout() {
  const status = useAuthStore((s) => s.status);

  if (status === 'authenticated') {
    return <Redirect href="/(app)/(tabs)/home" />;
  }

  return (
    <Stack>
      <Stack.Screen name="login" options={{ title: 'Login' }} />
      <Stack.Screen name="register" options={{ title: 'Register' }} />
      <Stack.Screen name="forgot-password" options={{ title: 'Forgot password' }} />
    </Stack>
  );
}
