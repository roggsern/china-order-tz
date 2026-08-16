import { Ionicons } from '@expo/vector-icons';
import { Tabs, Redirect } from 'expo-router';

import { useAdminAuthStore } from '@/src/core/auth';
import { canAccessTab } from '@/src/features/nav/navAuthorization';
import { colors } from '@/src/shared/theme/colors';

export default function TabsLayout() {
  const admin = useAdminAuthStore((s) => s.admin);
  const status = useAdminAuthStore((s) => s.status);

  if (status !== 'authenticated') {
    return <Redirect href="/(auth)/login" />;
  }

  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: colors.navy },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: '700' },
        tabBarActiveTintColor: colors.gold,
        tabBarInactiveTintColor: '#94a3b8',
        tabBarStyle: { backgroundColor: colors.navy, borderTopColor: colors.navyMuted },
      }}
    >
      <Tabs.Screen
        name="dashboard"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="grid-outline" color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="orders"
        options={{
          title: 'Orders',
          href: canAccessTab(admin, 'orders') ? undefined : null,
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="receipt-outline" color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="support"
        options={{
          title: 'Support',
          href: canAccessTab(admin, 'support') ? undefined : null,
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="chatbubbles-outline" color={color} size={size} />
          ),
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: 'More',
          headerShown: false,
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="menu-outline" color={color} size={size} />
          ),
        }}
      />
    </Tabs>
  );
}
