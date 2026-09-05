import { fireEvent, render, screen } from '@testing-library/react-native';
import { QuantitySelector } from './QuantitySelector';

describe('QuantitySelector', () => {
  it('does not cap at 99 when sellable stock is higher', async () => {
    const onChange = jest.fn();
    await render(
      <QuantitySelector quantity={99} onChange={onChange} max={250} />,
    );

    fireEvent.press(screen.getByLabelText('Increase quantity'));
    expect(onChange).toHaveBeenCalledWith(100);
  });

  it('stops at the provided sellable max, not a hardcoded 99', async () => {
    const onChange = jest.fn();
    await render(
      <QuantitySelector quantity={250} onChange={onChange} max={250} />,
    );

    fireEvent.press(screen.getByLabelText('Increase quantity'));
    expect(onChange).not.toHaveBeenCalled();
  });
});
