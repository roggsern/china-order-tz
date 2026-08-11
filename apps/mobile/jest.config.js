/** @type {import('jest').Config} */
module.exports = {
  preset: 'jest-expo',
  testMatch: ['**/__tests__/**/*.(test|spec).(ts|tsx)', '**/*.(test|spec).(ts|tsx)'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/$1',
  },
  clearMocks: true,
  collectCoverageFrom: [
    'src/core/**/*.{ts,tsx}',
    '!src/core/**/index.ts',
  ],
};
