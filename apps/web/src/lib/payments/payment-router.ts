import type { PaymentMethodCode } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES } from "@/lib/types/payment";
import { mpesaProvider } from "@/lib/payments/providers/mpesa-provider";
import { nmbProvider } from "@/lib/payments/providers/nmb-provider";
import { selcomProvider } from "@/lib/payments/providers/selcom-provider";
import type {
  NormalizedPaymentResponse,
  PaymentCallbackResult,
  PaymentInitiateContext,
  PaymentProvider,
  PaymentProviderCode,
} from "@/lib/payments/providers/types";
import { PAYMENT_PROVIDER } from "@/lib/payments/providers/types";
import { getTransaction } from "@/lib/payments/transaction-store";
import { PAYMENT_TRANSACTION_STATUS, type VerifyPaymentResult } from "@/lib/payments/types";

const providers: PaymentProvider[] = [mpesaProvider, nmbProvider, selcomProvider];

const providerMap = new Map<PaymentProviderCode, PaymentProvider>(
  providers.map((provider) => [provider.code, provider]),
);

/** Maps checkout payment method codes to gateway providers. */
export function mapPaymentMethodToProvider(
  method: PaymentMethodCode,
): PaymentProviderCode | null {
  switch (method) {
    case PAYMENT_METHOD_CODES.MPESA:
      return PAYMENT_PROVIDER.MPESA;
    case PAYMENT_METHOD_CODES.NMB:
      return PAYMENT_PROVIDER.NMB;
    case PAYMENT_METHOD_CODES.SELCOM:
      return PAYMENT_PROVIDER.SELCOM;
    default:
      return null;
  }
}

export function isGatewayPaymentMethod(method: PaymentMethodCode): boolean {
  return mapPaymentMethodToProvider(method) !== null;
}

export class PaymentRouter {
  getProvider(code: PaymentProviderCode): PaymentProvider {
    const provider = providerMap.get(code);
    if (!provider) {
      throw new Error(`Unknown payment provider: ${code}`);
    }
    return provider;
  }

  listProviders(): PaymentProviderCode[] {
    return [...providerMap.keys()];
  }

  async initiatePayment(
    providerCode: PaymentProviderCode,
    context: PaymentInitiateContext,
  ): Promise<NormalizedPaymentResponse> {
    const provider = this.getProvider(providerCode);
    return provider.initiatePayment(context);
  }

  async initiateByMethod(
    method: PaymentMethodCode,
    context: PaymentInitiateContext,
  ): Promise<NormalizedPaymentResponse> {
    const providerCode = mapPaymentMethodToProvider(method);
    if (!providerCode) {
      return {
        success: false,
        transactionId: null,
        message: `Payment method "${method}" does not use a gateway provider.`,
        status: PAYMENT_TRANSACTION_STATUS.FAILED,
        checkoutRequestId: null,
        mode: "test",
        provider: PAYMENT_PROVIDER.MPESA,
      };
    }
    return this.initiatePayment(providerCode, context);
  }

  identifyProviderFromCallback(payload: unknown): PaymentProvider | null {
    for (const provider of providers) {
      if (provider.canHandleCallback(payload)) {
        return provider;
      }
    }
    return null;
  }

  async handleCallback(payload: unknown): Promise<PaymentCallbackResult> {
    const provider = this.identifyProviderFromCallback(payload);
    if (!provider) {
      return {
        handled: false,
        message: "Unable to identify payment provider from callback payload.",
      };
    }
    return provider.handleCallback(payload);
  }

  verifyPayment(transactionId: string): VerifyPaymentResult | null {
    const transaction = getTransaction(transactionId);
    if (!transaction) {
      return null;
    }

    const provider = providerMap.get(transaction.provider);
    return provider?.verifyPayment(transactionId) ?? null;
  }
}

export const paymentRouter = new PaymentRouter();
