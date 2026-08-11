import { journeyLabelFromChannel } from './journeyLabel';

describe('journeyLabelFromChannel', () => {
  it('maps CHINA_IMPORT and TZ_LOCAL without exposing scope= values', () => {
    expect(journeyLabelFromChannel('CHINA_IMPORT')).toBe('Order from China');
    expect(journeyLabelFromChannel('TZ_LOCAL')).toBe('Buy from TZ');
    expect(journeyLabelFromChannel('CHINA_IMPORT')).not.toBe('china');
    expect(journeyLabelFromChannel('TZ_LOCAL')).not.toBe('tz');
  });
});
