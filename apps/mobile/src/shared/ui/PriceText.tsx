import { Text, type StyleProp, type TextStyle } from 'react-native';
import { formatCustomerMoney } from '../utils/formatCustomerMoney';
import { typography } from '../theme';

type Props = {
  value: string | number | null | undefined;
  currency?: string;
  size?: 'default' | 'large';
  style?: StyleProp<TextStyle>;
  /** Optional accessibility prefix, e.g. "Price". */
  accessibilityLabelPrefix?: string;
};

/**
 * Formats server amounts only — never recalculates totals.
 */
export function PriceText({
  value,
  currency = 'TZS',
  size = 'default',
  style,
  accessibilityLabelPrefix = 'Price',
}: Props) {
  const formatted = formatCustomerMoney(value, currency);
  return (
    <Text
      accessibilityLabel={`${accessibilityLabelPrefix} ${formatted}`}
      style={[size === 'large' ? typography.priceLarge : typography.price, style]}
    >
      {formatted}
    </Text>
  );
}
