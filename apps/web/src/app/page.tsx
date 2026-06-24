import { Header } from "@/components/home/Header";
import { Hero } from "@/components/home/Hero";
import { Categories } from "@/components/home/Categories";
import { HowItWorks } from "@/components/home/HowItWorks";
import { OrderFromChina } from "@/components/home/OrderFromChina";
import { FeaturedProducts } from "@/components/home/FeaturedProducts";
import { WhyChooseUs } from "@/components/home/WhyChooseUs";
import { Newsletter } from "@/components/home/Newsletter";
import { Footer } from "@/components/home/Footer";

export default function Home() {
  return (
    <div className="min-h-screen bg-white text-zinc-900">
      <Header />
      <main>
        <Hero />
        <Categories />
        <HowItWorks />
        <OrderFromChina />
        <FeaturedProducts />
        <WhyChooseUs />
        <Newsletter />
      </main>
      <Footer />
    </div>
  );
}
