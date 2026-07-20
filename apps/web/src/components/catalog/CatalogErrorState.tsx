"use client";

import { useRouter } from "next/navigation";
import { ErrorState, type ErrorStateKind } from "@/components/ui/ErrorState";

interface CatalogErrorStateProps {
  title?: string;
  message?: string;
  className?: string;
  kind?: ErrorStateKind;
}

export function CatalogErrorState({
  title,
  message,
  className = "",
  kind,
}: CatalogErrorStateProps) {
  const router = useRouter();

  return (
    <ErrorState
      kind={kind}
      title={title}
      message={message}
      className={className}
      onRetry={() => router.refresh()}
    />
  );
}
