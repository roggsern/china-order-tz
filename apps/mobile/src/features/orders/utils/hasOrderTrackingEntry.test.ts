import { hasOrderTrackingEntry } from './hasOrderTrackingEntry';

describe('hasOrderTrackingEntry', () => {
  it('hides tracking when shipment data is absent', () => {
    expect(hasOrderTrackingEntry({ shipment: null })).toBe(false);
    expect(hasOrderTrackingEntry({})).toBe(false);
    expect(
      hasOrderTrackingEntry({
        shipment: {
          status: null,
          statusLabel: null,
          trackingReference: null,
          carrierName: null,
        },
      }),
    ).toBe(false);
  });

  it('shows tracking when server provides shipment signals', () => {
    expect(
      hasOrderTrackingEntry({
        shipment: {
          status: 'in_transit',
          statusLabel: null,
          trackingReference: null,
          carrierName: null,
        },
      }),
    ).toBe(true);
    expect(
      hasOrderTrackingEntry({
        shipment: {
          status: null,
          statusLabel: null,
          trackingReference: 'DHL-1',
          carrierName: null,
        },
      }),
    ).toBe(true);
  });
});
