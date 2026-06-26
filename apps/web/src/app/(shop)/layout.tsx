import { StorefrontShell } from "@/components/layout/StorefrontShell";

export default function ShopLayout({ children }: { children: React.ReactNode }) {
  return <StorefrontShell>{children}</StorefrontShell>;
}
