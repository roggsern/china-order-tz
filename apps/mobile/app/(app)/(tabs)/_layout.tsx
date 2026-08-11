import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import type { ColorValue } from 'react-native';

import { resolveTabIconName } from '@/src/shared/navigation/tabIcons';

function TabBarIcon({
  routeName,
  color,
  size,
  focused,
}: {
  routeName: string;
  color: ColorValue;
  size: number;
  focused: boolean;
}) {
  return (
    <Ionicons
      name={resolveTabIconName(routeName, focused) as keyof typeof Ionicons.glyphMap}
      size={size}
      color={color}
    />
  );
}

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={({ route }) => ({
        headerShown: true,
        tabBarActiveTintColor: '#0a7ea4',
        tabBarInactiveTintColor: '#666',
        tabBarIcon: ({ color, size, focused }) => (
          <TabBarIcon
            routeName={route.name}
            color={color}
            size={size}
            focused={focused}
          />
        ),
      })}
    >
      <Tabs.Screen name="home" options={{ title: 'Home' }} />
      <Tabs.Screen name="search" options={{ title: 'Search' }} />
      <Tabs.Screen name="browse" options={{ title: 'Browse' }} />
      <Tabs.Screen name="cart" options={{ title: 'Cart' }} />
      <Tabs.Screen name="orders" options={{ title: 'Orders' }} />
      <Tabs.Screen name="account" options={{ title: 'Account' }} />
    </Tabs>
  );
}
