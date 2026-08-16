import { StyleSheet, Text, View } from 'react-native';

import { colors, radii, spacing } from '@/src/shared/theme/colors';

type ChannelBadgeProps = {
  channel?: string | null;
};

export function ChannelBadge({ channel }: ChannelBadgeProps) {
  const code = channel?.toUpperCase();
  const isChina = code === 'CHINA_IMPORT';
  const isLocal = code === 'TZ_LOCAL';

  const label = isChina ? 'China Import' : isLocal ? 'TZ Local' : code ?? 'Unknown';
  const style = isChina ? styles.china : isLocal ? styles.local : styles.unknown;
  const textStyle = isChina ? styles.chinaText : isLocal ? styles.localText : styles.unknownText;

  return (
    <View style={[styles.badge, style]}>
      <Text style={[styles.text, textStyle]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    alignSelf: 'flex-start',
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: radii.sm,
  },
  text: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
  china: { backgroundColor: colors.chinaImportBg },
  chinaText: { color: colors.chinaImport },
  local: { backgroundColor: colors.tzLocalBg },
  localText: { color: colors.tzLocal },
  unknown: { backgroundColor: colors.surfaceMuted },
  unknownText: { color: colors.textMuted },
});
