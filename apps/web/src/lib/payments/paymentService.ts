import { isPaymentTestMode } from "@/lib/payments/config";
import {
  createTestStkPushResponse,
  generateTestMpesaReceipt,
  normalizeMpesaPhone,
} from "@/lib/payments/mpesa";
import {
  mapPaymentMethodToProvider,
  paymentRouter,
} from "@/lib/payments/payment-router";
import { PAYMENT_PROVIDER } from "@/lib/payments/providers/types";
import { saveTransaction, updateTransactionStatus } from "@/lib/payments/transaction-store";
import type {
  InitiateStkPushInput,
  InitiateStkPushResult,
  MpesaCallbackResult,
  MpesaStkCallbackPayload,
  PaymentTransaction,
  SimulateStkPushResult,
  VerifyPaymentResult,
} from "@/lib/payments/types";
import { PAYMENT_TRANSACTION_STATUS } from "@/lib/payments/types";
import type { PaymentMethodCode } from "@/lib/types/payment";
import { generateTransactionId } from "@/lib/payments/providers/utils";

function resolveProviderCode(input: InitiateStkPushInput) {
  if (input.provider) {
    return input.provider;
  }
  if (input.paymentMethod) {
    return mapPaymentMethodToProvider(input.paymentMethod as PaymentMethodCode);
  }
  return PAYMENT_PROVIDER.MPESA;
}

/**
 * Server-side payment orchestrator — delegates to PaymentRouter.
 * Used by API routes — not imported from client components.
 */
export class PaymentService {
  /** Initiate payment via the appropriate gateway provider. */
  async initiatePayment(input: InitiateStkPushInput): Promise<InitiateStkPushResult> {
    const providerCode = resolveProviderCode(input) ?? PAYMENT_PROVIDER.MPESA;
    const result = await paymentRouter.initiatePayment(providerCode, {
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      amount: input.amount,
      phone: input.phone,
      accountReference: input.accountReference,
      description: input.description,
    });

    return {
      success: result.success,
      transactionId: result.transactionId,
      status: result.status,
      checkoutRequestId: result.checkoutRequestId,
      message: result.message,
      mode: result.mode,
      provider: result.provider,
    };
  }

  /** @deprecated Use initiatePayment — kept for backward compatibility. */
  async initiateSTKPush(input: InitiateStkPushInput): Promise<InitiateStkPushResult> {
    return this.initiatePayment({ ...input, provider: PAYMENT_PROVIDER.MPESA });
  }

  verifyPayment(transactionId: string): VerifyPaymentResult | null {
    return paymentRouter.verifyPayment(transactionId);
  }

  async handleCallback(payload: unknown): Promise<MpesaCallbackResult> {
    return paymentRouter.handleCallback(payload);
  }

  /** Instant test payment — bypasses polling (test mode only, M-Pesa). */
  simulateSTKPush(input: InitiateStkPushInput): SimulateStkPushResult {
    if (!isPaymentTestMode()) {
      throw new Error("Simulate payment is only available when PAYMENT_MODE=test.");
    }

    const transactionId = generateTransactionId();
    const paymentReference = generateTestMpesaReceipt();
    const now = new Date().toISOString();
    const normalizedPhone = normalizeMpesaPhone(input.phone) || input.phone;
    const stk = createTestStkPushResponse();

    const paid: PaymentTransaction = {
      transactionId,
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      amount: input.amount,
      phone: normalizedPhone,
      status: PAYMENT_TRANSACTION_STATUS.PAID,
      paymentReference,
      checkoutRequestId: stk.CheckoutRequestID,
      merchantRequestId: stk.MerchantRequestID,
      provider: PAYMENT_PROVIDER.MPESA,
      mode: "test",
      failureReason: null,
      createdAt: now,
      updatedAt: now,
    };

    saveTransaction(paid);
    updateTransactionStatus(transactionId, {
      status: PAYMENT_TRANSACTION_STATUS.PAID,
      paymentReference,
      failureReason: null,
    });

    return {
      success: true,
      transactionId,
      paymentReference,
      status: PAYMENT_TRANSACTION_STATUS.PAID,
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      amount: input.amount,
      message: "Test payment simulated successfully. No real money was charged.",
      mode: "test",
    };
  }

  /** @deprecated Use handleCallback */
  handlePaymentCallback(payload: MpesaStkCallbackPayload): Promise<MpesaCallbackResult> {
    return this.handleCallback(payload);
  }

  /** @deprecated Use simulateSTKPush */
  simulatePayment(input: InitiateStkPushInput): SimulateStkPushResult {
    return this.simulateSTKPush(input);
  }
}

export const paymentService = new PaymentService();

/** @deprecated Use paymentService */
export const serverPaymentGateway = paymentService;

/** @deprecated Use paymentService.initiatePayment */
export async function initiatePayment(input: InitiateStkPushInput): Promise<InitiateStkPushResult> {
  return paymentService.initiatePayment(input);
}
