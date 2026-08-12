import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import { normalizeCustomerPlainText } from '@/src/shared/utils/normalizeCustomerPlainText';
import { Card } from '@/src/shared/ui/Card';
import { colors, spacing, typography } from '@/src/shared/theme';
import type { CatalogProductDetail, ProductSpecification } from '../models/types';
import type { ProductReview } from '../api/productReviewsApi';

type SectionKey = 'description' | 'specs' | 'shipping' | 'reviews';

type Props = {
  product: CatalogProductDetail;
  reviews: ProductReview[];
  reviewsLoading?: boolean;
  reviewsError?: boolean;
  onRetryReviews?: () => void;
};

function buildSpecRows(product: CatalogProductDetail): ProductSpecification[] {
  const rows = [...(product.specifications ?? [])];
  if (rows.length === 0) {
    if (product.dimensions) {
      rows.push({ label: 'Dimensions', value: String(product.dimensions) });
    }
    if (product.weight != null && String(product.weight).trim() !== '') {
      rows.push({ label: 'Weight', value: String(product.weight) });
    }
  }
  return rows;
}

function SectionHeader({
  title,
  open,
  onPress,
  meta,
}: {
  title: string;
  open: boolean;
  onPress: () => void;
  meta?: string | null;
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ expanded: open }}
      style={styles.header}
    >
      <View style={styles.headerCopy}>
        <Text style={styles.headerTitle}>{title}</Text>
        {meta ? <Text style={styles.headerMeta}>{meta}</Text> : null}
      </View>
      <Text style={styles.chevron}>{open ? '−' : '+'}</Text>
    </Pressable>
  );
}

/**
 * Informational PDP sections — below purchase controls.
 * Only renders real API data; empty sections are omitted.
 */
export function ProductInfoSections({
  product,
  reviews,
  reviewsLoading,
  reviewsError,
  onRetryReviews,
}: Props) {
  const [open, setOpen] = useState<SectionKey | null>('description');
  const description = normalizeCustomerPlainText(product.description ?? '');
  const specs = buildSpecRows(product);
  const hasShipping =
    product.shippingPrices &&
    (product.shippingPrices.air != null || product.shippingPrices.sea != null);
  const ratingMeta =
    product.averageRating != null || product.reviewCount != null
      ? [
          product.averageRating != null
            ? `${product.averageRating.toFixed(1)}★`
            : null,
          product.reviewCount != null
            ? `${product.reviewCount} review${product.reviewCount === 1 ? '' : 's'}`
            : null,
        ]
          .filter(Boolean)
          .join(' · ')
      : null;

  function toggle(key: SectionKey) {
    setOpen((current) => (current === key ? null : key));
  }

  const hasAny =
    Boolean(description) ||
    specs.length > 0 ||
    Boolean(hasShipping) ||
    ratingMeta != null ||
    reviews.length > 0 ||
    reviewsLoading ||
    reviewsError;

  if (!hasAny) return null;

  return (
    <View style={styles.wrap}>
      {description ? (
        <Card elevated={false} style={styles.card}>
          <SectionHeader
            title="Description"
            open={open === 'description'}
            onPress={() => toggle('description')}
          />
          {open === 'description' ? (
            <Text style={styles.body}>{description}</Text>
          ) : null}
        </Card>
      ) : null}

      {specs.length > 0 ? (
        <Card elevated={false} style={styles.card}>
          <SectionHeader
            title="Specifications"
            open={open === 'specs'}
            onPress={() => toggle('specs')}
          />
          {open === 'specs'
            ? specs.map((row) => (
                <View key={`${row.label}-${row.value}`} style={styles.specRow}>
                  <Text style={styles.specLabel}>{row.label}</Text>
                  <Text style={styles.specValue}>{row.value}</Text>
                </View>
              ))
            : null}
        </Card>
      ) : null}

      {hasShipping ? (
        <Card elevated={false} style={styles.card}>
          <SectionHeader
            title="Shipping"
            open={open === 'shipping'}
            onPress={() => toggle('shipping')}
          />
          {open === 'shipping' ? (
            <View style={styles.shippingBody}>
              {product.requiresChinaShipping ? (
                <Text style={styles.body}>
                  China import shipping options from the product record.
                </Text>
              ) : null}
              {product.shippingPrices?.air != null ? (
                <Text style={styles.meta}>
                  Air: {formatCustomerMoney(product.shippingPrices.air, 'TZS')}
                </Text>
              ) : null}
              {product.shippingPrices?.sea != null ? (
                <Text style={styles.meta}>
                  Sea: {formatCustomerMoney(product.shippingPrices.sea, 'TZS')}
                </Text>
              ) : null}
            </View>
          ) : null}
        </Card>
      ) : null}

      {ratingMeta != null ||
      reviews.length > 0 ||
      reviewsLoading ||
      reviewsError ? (
        <Card elevated={false} style={styles.card}>
          <SectionHeader
            title="Reviews"
            open={open === 'reviews'}
            onPress={() => toggle('reviews')}
            meta={ratingMeta}
          />
          {open === 'reviews' ? (
            <View style={styles.reviewsBody}>
              {reviewsLoading ? (
                <Text style={styles.meta}>Loading reviews…</Text>
              ) : null}
              {reviewsError ? (
                <Pressable onPress={onRetryReviews}>
                  <Text style={styles.error}>
                    Could not load reviews. Tap to retry.
                  </Text>
                </Pressable>
              ) : null}
              {!reviewsLoading && !reviewsError && reviews.length === 0 ? (
                <Text style={styles.meta}>No approved reviews yet.</Text>
              ) : null}
              {reviews.map((review) => (
                <View key={review.id} style={styles.reviewItem}>
                  <Text style={styles.reviewTitle}>
                    {review.rating}★
                    {review.title ? ` · ${review.title}` : ''}
                  </Text>
                  {review.author ? (
                    <Text style={styles.meta}>{review.author}</Text>
                  ) : null}
                  {review.comment ? (
                    <Text style={styles.body}>
                      {normalizeCustomerPlainText(review.comment)}
                    </Text>
                  ) : null}
                </View>
              ))}
            </View>
          ) : null}
        </Card>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginTop: spacing.xl,
    gap: spacing.sm,
  },
  card: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    paddingVertical: spacing.sm,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: spacing.xs,
  },
  headerCopy: { flex: 1 },
  headerTitle: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
  },
  headerMeta: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
  chevron: {
    ...typography.title,
    color: colors.primaryPressed,
    fontSize: 22,
    lineHeight: 24,
  },
  body: {
    ...typography.body,
    color: colors.text,
    marginTop: spacing.sm,
  },
  meta: {
    ...typography.caption,
    marginTop: spacing.xs,
  },
  shippingBody: {
    marginTop: spacing.xs,
  },
  specRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: spacing.xs,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  specLabel: {
    ...typography.caption,
    flex: 1,
    color: colors.textMuted,
  },
  specValue: {
    ...typography.bodyStrong,
    flex: 1,
    textAlign: 'right',
    color: colors.text,
  },
  reviewsBody: {
    marginTop: spacing.xs,
  },
  reviewItem: {
    marginTop: spacing.md,
    paddingTop: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  reviewTitle: {
    ...typography.bodyStrong,
  },
  error: {
    ...typography.caption,
    color: colors.error,
    marginTop: spacing.xs,
  },
});
