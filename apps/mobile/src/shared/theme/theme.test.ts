import {
  brandColors,
  colors,
  darkColors,
  LEGACY_TEAL_ACCENT,
  lightColors,
  radius,
  spacing,
  typography,
} from './index';

describe('mobile design system tokens', () => {
  it('uses web brand gold as the primary accent', () => {
    expect(brandColors.gold).toBe('#c9a227');
    expect(colors.primary).toBe(brandColors.gold);
    expect(lightColors.primary).toBe('#c9a227');
  });

  it('keeps a dark palette reserved for future mode switching', () => {
    expect(darkColors.background).toBe('#0a0a0a');
    expect(darkColors.primary).toBe(brandColors.goldLight);
  });

  it('documents the legacy teal accent without using it as primary or info', () => {
    expect(LEGACY_TEAL_ACCENT).toBe('#0a7ea4');
    expect(colors.primary).not.toBe(LEGACY_TEAL_ACCENT);
    expect(colors.info).toBe(brandColors.tzBlue);
    expect(colors.info).not.toBe(LEGACY_TEAL_ACCENT);
  });

  it('exposes a consistent spacing and radius scale', () => {
    expect(spacing.lg).toBe(16);
    expect(spacing.xxl).toBe(24);
    expect(radius.lg).toBe(10);
    expect(radius.xl).toBe(12);
  });

  it('defines typography hierarchy used by foundation components', () => {
    expect(typography.display.fontSize).toBe(28);
    expect(typography.heading.fontSize).toBe(22);
    expect(typography.title.fontSize).toBe(18);
    expect(typography.body.fontSize).toBe(15);
    expect(typography.caption.fontSize).toBe(12);
    expect(typography.price.color).toBe(colors.primary);
  });
});
