import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  poweredByHeader: false,
  reactStrictMode: true,
  compress: true,
  async rewrites() {
    return {
      afterFiles: [
        {
          source: "/api/catalog/products/:slug/configuration",
          destination: "/api/catalog/products/configuration?slug=:slug",
        },
        {
          source: "/api/catalog/products/:slug/quote",
          destination: "/api/catalog/products/quote?slug=:slug",
        },
        {
          source: "/api/catalog/products/:slug",
          destination: "/api/catalog/products?slug=:slug",
        },
      ],
    };
  },
};

export default nextConfig;
