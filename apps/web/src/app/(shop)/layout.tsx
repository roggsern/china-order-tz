import { Header } from "@/components/home/Header";
import { Footer } from "@/components/home/Footer";

export default function ShopLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-white text-zinc-900">
      <Header />
      <main>{children}</main>
      <Footer />
    </div>
  );
}
