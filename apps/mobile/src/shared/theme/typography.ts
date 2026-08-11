import type { TextStyle } from 'react-native';
import { colors } from './colors';

/**
 * Typography hierarchy for premium ecommerce surfaces.
 * System fonts for now; brand webfonts can plug in later via fontFamily.
 */
export const fontSize = {
  display: 28,
  heading: 22,
  title: 18,
  body: 15,
  bodySmall: 14,
  caption: 12,
  price: 16,
  priceLarge: 20,
  label: 13,
} as const;

export const fontWeight = {
  regular: '400' as TextStyle['fontWeight'],
  medium: '500' as TextStyle['fontWeight'],
  semibold: '600' as TextStyle['fontWeight'],
  bold: '700' as TextStyle['fontWeight'],
};

export const lineHeight = {
  display: 34,
  heading: 28,
  title: 24,
  body: 22,
  caption: 16,
  price: 22,
} as const;

export const typography = {
  display: {
    fontSize: fontSize.display,
    fontWeight: fontWeight.bold,
    lineHeight: lineHeight.display,
    color: colors.text,
  } satisfies TextStyle,
  heading: {
    fontSize: fontSize.heading,
    fontWeight: fontWeight.bold,
    lineHeight: lineHeight.heading,
    color: colors.text,
  } satisfies TextStyle,
  title: {
    fontSize: fontSize.title,
    fontWeight: fontWeight.bold,
    lineHeight: lineHeight.title,
    color: colors.text,
  } satisfies TextStyle,
  body: {
    fontSize: fontSize.body,
    fontWeight: fontWeight.regular,
    lineHeight: lineHeight.body,
    color: colors.textSecondary,
  } satisfies TextStyle,
  bodyStrong: {
    fontSize: fontSize.body,
    fontWeight: fontWeight.semibold,
    lineHeight: lineHeight.body,
    color: colors.text,
  } satisfies TextStyle,
  caption: {
    fontSize: fontSize.caption,
    fontWeight: fontWeight.regular,
    lineHeight: lineHeight.caption,
    color: colors.textMuted,
  } satisfies TextStyle,
  label: {
    fontSize: fontSize.label,
    fontWeight: fontWeight.semibold,
    color: colors.textSecondary,
  } satisfies TextStyle,
  price: {
    fontSize: fontSize.price,
    fontWeight: fontWeight.bold,
    lineHeight: lineHeight.price,
    color: colors.primary,
  } satisfies TextStyle,
  priceLarge: {
    fontSize: fontSize.priceLarge,
    fontWeight: fontWeight.bold,
    lineHeight: 26,
    color: colors.primary,
  } satisfies TextStyle,
  link: {
    fontSize: fontSize.body,
    fontWeight: fontWeight.semibold,
    color: colors.primary,
  } satisfies TextStyle,
} as const;
