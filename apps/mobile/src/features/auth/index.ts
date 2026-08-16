export { loginWithPassword, registerAccount, logout } from './services/authSession';
export { getAuthErrorMessage, getAuthFieldErrors } from './utils/authErrorMessage';
export { SplashView } from './components/SplashView';
export { ForgotPasswordScreen } from './components/ForgotPasswordScreen';
export {
  loginRequestSchema,
  registerRequestSchema,
  authSessionResponseSchema,
  type LoginRequest,
  type RegisterRequest,
} from './api/schemas';
export {
  forgotPasswordRequestSchema,
  requestPasswordReset,
  type ForgotPasswordRequest,
  type ForgotPasswordResult,
} from './api/forgotPasswordApi';
export {
  resendEmailVerification,
  refreshAuthenticatedUser,
  type ResendEmailVerificationResult,
} from './api/emailVerificationApi';
export {
  buildAuthWebUrl,
  resolveAuthWebBaseUrl,
  type AuthWebPath,
} from './utils/authWebLinks';
