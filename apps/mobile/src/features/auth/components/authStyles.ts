import { StyleSheet } from 'react-native';
import { colors, radius, spacing, typography } from '@/src/shared/theme';

/** Shared auth form chrome — presentation only. */
export const authStyles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    padding: spacing.xxl,
    paddingBottom: spacing.huge,
  },
  heading: {
    ...typography.heading,
    marginBottom: spacing.sm,
  },
  subheading: {
    ...typography.body,
    marginBottom: spacing.xxl,
  },
  label: {
    ...typography.label,
    marginBottom: spacing.xs,
    color: colors.text,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    ...typography.bodyStrong,
    marginBottom: spacing.xs,
    backgroundColor: colors.backgroundMuted,
    color: colors.text,
  },
  inputError: {
    borderColor: colors.error,
  },
  fieldError: {
    ...typography.caption,
    color: colors.error,
    marginBottom: spacing.md,
  },
  fieldSpacer: {
    marginBottom: spacing.md,
  },
  banner: {
    backgroundColor: colors.errorMuted,
    borderRadius: radius.lg,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  bannerText: {
    ...typography.body,
    color: colors.error,
  },
  button: {
    backgroundColor: colors.primary,
    borderRadius: radius.lg,
    paddingVertical: spacing.md,
    alignItems: 'center',
    marginTop: spacing.sm,
    minHeight: 48,
    justifyContent: 'center',
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    ...typography.bodyStrong,
    color: colors.onPrimary,
    fontWeight: '700',
  },
  linkRow: {
    marginTop: spacing.xl,
    alignItems: 'center',
  },
  linkText: {
    ...typography.bodyStrong,
    color: colors.primaryPressed,
  },
});
