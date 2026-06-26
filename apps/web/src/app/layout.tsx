import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { CartProvider } from "@/components/cart/CartProvider";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "CHINA ORDER TZ — Import Products Directly From China",
  description:
    "Shop premium products imported directly from China to Tanzania. Fast shipping, trusted suppliers, affordable prices, and secure payments.",
  icons: {
    icon: [
      { url: "/favicon.ico", sizes: "any" },
      { url: "/branding/favicon.png", type: "image/png", sizes: "48x48" },
      { url: "/branding/icon-192.png", type: "image/png", sizes: "192x192" },
      { url: "/branding/icon-512.png", type: "image/png", sizes: "512x512" },
    ],
    apple: "/branding/apple-touch-icon.png",
  },
  manifest: "/manifest.webmanifest",
  openGraph: {
    title: "CHINA ORDER TZ — Import Products Directly From China",
    description:
      "Shop premium products imported directly from China to Tanzania. Fast shipping, trusted suppliers, affordable prices, and secure payments.",
    siteName: "CHINA ORDER TZ",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className={`${geistSans.variable} ${geistMono.variable} antialiased`}>
        <CartProvider>{children}</CartProvider>
      </body>
    </html>
  );
}
