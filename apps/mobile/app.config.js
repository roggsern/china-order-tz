/**
 * Expo app config — wraps app.json so release builds can inject
 * google-services.json via EAS file env without changing bundle identity.
 *
 * Local/default: ./google-services.json (may still be present for local builds).
 * EAS production (recommended): set file env GOOGLE_SERVICES_JSON to the Firebase
 * Android client config path provided by EAS Secrets / Environment Variables.
 *
 * Owner action before untracking the repo file:
 *   eas env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --environment production
 * Then remove the tracked file and keep it gitignored.
 */
const appJson = require('./app.json');

function resolveGoogleServicesFile() {
  const fromEnv =
    process.env.GOOGLE_SERVICES_JSON ||
    process.env.GOOGLE_SERVICES_FILE ||
    process.env.EXPO_PUBLIC_GOOGLE_SERVICES_FILE;
  if (typeof fromEnv === 'string' && fromEnv.trim() !== '') {
    return fromEnv.trim();
  }
  return appJson.expo?.android?.googleServicesFile || './google-services.json';
}

module.exports = ({ config }) => {
  const expo = appJson.expo;
  return {
    ...config,
    ...expo,
    android: {
      ...config.android,
      ...expo.android,
      googleServicesFile: resolveGoogleServicesFile(),
    },
  };
};
