import { ConfidenceStrip } from "@/components/ui/ConfidenceStrip";

interface CartTrustStripProps {
  className?: string;
}

export function CartTrustStrip({ className = "" }: CartTrustStripProps) {
  return <ConfidenceStrip variant="grid" className={className} />;
}
