export function paymentSelectorHeading(): string {
  return 'Choose payment method';
}

export function paymentSelectorSubheading(): string {
  return "Choose how you'd like to pay.";
}

export function paymentProcessingHeading(): string {
  return 'Payment is still processing';
}

export function paymentProcessingSubheading(methodLabel: string): string {
  const method = methodLabel.trim() || 'payment';
  return `Continue your ${method} payment. You can safely return later and continue.`;
}

export function paymentConfirmedSubheading(): string {
  return 'Payment confirmed. View your order for updates and tracking.';
}

export function paymentOfficeSubheading(): string {
  return 'Pay at a CHINA ORDER TZ office. Your order stays unpaid until payment is confirmed at the office.';
}

export function paymentOfficeStatusNote(): string {
  return 'Pay at Office. Your order stays unpaid until payment is confirmed at the office.';
}

export function paymentStillProcessingNote(): string {
  return 'Your payment is still being processed.';
}

export function paymentPromptSentNote(): string {
  return 'Payment request sent. Approve it on your phone when prompted.';
}

export function paymentEndedChooseAgainNote(): string {
  return 'The previous payment request ended. You can choose another payment method.';
}

export function paymentStatusCardNote(): string {
  return 'Status updates once payment is confirmed. This can take a little time.';
}

export function continueToPaymentNote(): string {
  return "Choose how you'd like to pay. Follow the instructions to complete your payment.";
}

export function paymentReturnHint(): string {
  return "We're confirming your payment. This can take a little time.";
}

export const PAYMENT_NEXT_STEPS_TITLE = "What's next";

export const PAYMENT_NEXT_STEPS = [
  {
    id: 'choice',
    title: 'Choose a method',
    description:
      'NMB, Mobile Money, or Pay at Office appear when they are available for your order.',
  },
  {
    id: 'confirm',
    title: 'Complete payment',
    description:
      'Follow the instructions, then check the status here. Closing a bank or wallet screen does not complete payment.',
  },
  {
    id: 'retry',
    title: 'Safe to continue',
    description: 'You can safely return later and continue if payment is still processing.',
  },
] as const;
