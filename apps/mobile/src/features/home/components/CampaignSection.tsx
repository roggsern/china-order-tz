import { StyleSheet, Text, View } from 'react-native';
import type { HomepageCampaignMeta } from '../models/types';
import { SectionHeader } from './SectionHeader';

type Props = {
  campaign: HomepageCampaignMeta;
};

/** Campaign summary from homepage `meta.campaign` (server-resolved). */
export function CampaignSection({ campaign }: Props) {
  return (
    <View style={styles.section}>
      <SectionHeader title="Campaign" subtitle="Active promotion from CMS" />
      <View style={styles.card}>
        <Text style={styles.name}>{campaign.name}</Text>
        <Text style={styles.meta}>Slug: {campaign.slug}</Text>
        <Text style={styles.meta}>Priority: {campaign.priority}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: 24,
  },
  card: {
    marginHorizontal: 16,
    padding: 16,
    borderRadius: 10,
    backgroundColor: '#fff6e8',
    borderWidth: 1,
    borderColor: '#f0d9a8',
  },
  name: {
    fontSize: 16,
    fontWeight: '700',
    marginBottom: 6,
    color: '#333',
  },
  meta: {
    fontSize: 13,
    color: '#666',
    marginTop: 2,
  },
});
