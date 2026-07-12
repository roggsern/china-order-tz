/** Laravel payment session response from POST /api/v1/payments/{id}/initiate */
export type NmbPaymentSessionResponse = {
  success: boolean;
  message: string;
  data: {
    payment_id: string;
    reference: string;
    order_id: string;
    order_number?: string | null;
    amount: number | string;
    currency: string;
    payment_method: string;
    status: string;
    gateway_session_id: string | null;
    gateway_reference: string | null;
    checkout_url: string | null;
    initiated_at: string | null;
  };
};

export type NmbCheckoutContext = {
  paymentId: string;
  orderId?: string | null;
  localOrderId?: string | null;
  gatewaySessionId?: string | null;
  successIndicator?: string | null;
  sessionVersion?: string | null;
  resultIndicator?: string | null;
};

export type NmbHostedCheckoutCallbacks = {
  onComplete?: (resultIndicator: string, sessionVersion?: string) => void;
  onCancel?: () => void;
  onError?: (error: NmbHostedCheckoutError) => void;
  onTimeout?: () => void;
};

export type NmbHostedCheckoutError = {
  cause?: string;
  explanation?: string;
  message?: string;
};

export type MpgsCheckoutInstance = {
  configure: (config: MpgsCheckoutConfigureInput) => void;
  showPaymentPage: () => void;
  showEmbeddedPage: (selector: string) => void;
};

export type MpgsCheckoutConfigureInput = {
  session: {
    id: string;
    version?: string;
  };
  interaction?: {
    merchant?: {
      name?: string;
      url?: string;
    };
  };
};

declare global {
  interface Window {
    Checkout?: MpgsCheckoutInstance;
  }
}
