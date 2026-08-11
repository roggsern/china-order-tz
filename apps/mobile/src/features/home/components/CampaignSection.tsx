import { StyleSheet, Text, View } from 'react-native';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { SectionHeader } from '@/src/shared/ui/SectionHeader';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { HomepageCampaignMeta } from '../models/types';

type Props = {
  campaign: HomepageCampaignMeta;
};

/** Campaign summary from homepage `meta.campaign` (server-resolved). */
export function CampaignSection({ campaign }: Props) {
  return (
    <View style={styles.section}>
      <SectionHeader title="Featured campaign" subtitle={null} />
      <View style={styles.pad}>
        <Card style={styles.card} elevated>
          <Badge label="Campaign" tone="brand" />
          <Text style={styles.name}>{campaign.name}</Text>
        </Card>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: spacing.xxl,
  },
  pad: {
    paddingHorizontal: spacing.lg,
  },
  card: {
    backgroundColor: colors.surfaceCream,
    borderColor: colors.primary,
    gap: spacing.sm,
  },
  name: {
    ...typography.title,
    fontSize: 17,
  },
});
