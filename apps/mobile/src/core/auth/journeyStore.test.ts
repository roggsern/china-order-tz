import { useJourneyStore } from '@/src/core/auth/journeyStore';

describe('useJourneyStore', () => {
  beforeEach(() => {
    useJourneyStore.setState({ journey: 'CHINA_IMPORT' });
  });

  it('defaults to CHINA_IMPORT', () => {
    expect(useJourneyStore.getState().journey).toBe('CHINA_IMPORT');
  });

  it('switches to TZ_LOCAL without renaming backend values', () => {
    useJourneyStore.getState().setJourney('TZ_LOCAL');
    expect(useJourneyStore.getState().journey).toBe('TZ_LOCAL');
  });

  it('TZ journey selection can be set for persistence', () => {
    useJourneyStore.getState().setJourney('TZ_LOCAL');
    expect(useJourneyStore.getState().journey).toBe('TZ_LOCAL');
  });
});
