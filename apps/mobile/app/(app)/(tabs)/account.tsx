import { router } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { useAuthStore } from '@/src/core/auth';
import { logout } from '@/src/features/auth';

export default function AccountScreen() {
  const user = useAuthStore((s) => s.user);
  const [busy, setBusy] = useState(false);

  async function onLogout() {
    setBusy(true);
    try {
      await logout();
      router.replace('/(auth)/login');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.container}>
      <Text style={styles.subtitle}>
        {user ? `Signed in as ${user.email}` : 'Not signed in'}
      </Text>
      {user?.name ? <Text style={styles.meta}>{user.name}</Text> : null}

      <Pressable
        style={[styles.button, busy ? styles.buttonDisabled : null]}
        onPress={() => void onLogout()}
        disabled={busy}
      >
        {busy ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>Log out</Text>
        )}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 24,
    backgroundColor: '#fff',
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 15,
    color: '#333',
    marginBottom: 4,
  },
  meta: {
    fontSize: 14,
    color: '#666',
    marginBottom: 24,
  },
  button: {
    marginTop: 16,
    backgroundColor: '#b00020',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
