"use client";

import { Header } from "@/components/home/Header";
import { Footer } from "@/components/home/Footer";
import { CustomerToastHost } from "@/components/customer/CustomerToastHost";

export function StorefrontShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-white text-zinc-900">
      <CustomerToastHost />
      <Header />
      <main>{children}</main>
      <Footer />
    </div>
  );
}
