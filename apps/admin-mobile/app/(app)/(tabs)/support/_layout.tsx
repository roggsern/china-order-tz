import { Stack } from 'expo-router';

export default function SupportLayout() {
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: '#0f2744' },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: '700' },
      }}
    />
  );
}
