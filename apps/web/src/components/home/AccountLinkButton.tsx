"use client";

import { AccountMenu } from "@/components/customer/AccountMenu";

interface AccountLinkButtonProps {
  className?: string;
  iconClassName?: string;
  showLabel?: boolean;
  labelClassName?: string;
}

export function AccountLinkButton(props: AccountLinkButtonProps) {
  return <AccountMenu {...props} />;
}
