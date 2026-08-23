export {
  createCustomerReturn,
  fetchCustomerReturn,
  fetchCustomerReturns,
} from './api/returnsApi';
export {
  useCreateReturnMutation,
  useCustomerReturnDetail,
  useCustomerReturnsList,
} from './hooks/useReturns';
export { ReturnRequestScreen } from './screens/ReturnRequestScreen';
export { ReturnsListScreen } from './screens/ReturnsListScreen';
export { ReturnDetailScreen } from './screens/ReturnDetailScreen';
export { shouldOfferReturnRequest } from './utils/returnEligibility';
export {
  resolveRefundDisplayStatus,
  resolveReturnDisplayStatus,
} from './utils/returnStatusDisplay';
export {
  buildOrderReturnHref,
  buildReturnDetailHref,
  buildReturnsListHref,
} from './utils/returnRoutes';
export type { CustomerReturnRequest, CreateReturnInput } from './models/types';
