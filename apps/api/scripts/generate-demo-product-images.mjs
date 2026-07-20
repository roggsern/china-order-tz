/**
 * Downloads royalty-free product photos (Unsplash License) into:
 *   database/assets/demo-products/
 *   storage/app/public/demo-products/
 *
 * Run from apps/api:
 *   node scripts/generate-demo-product-images.mjs
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const rootDir = path.resolve(fileURLToPath(new URL(".", import.meta.url)), "..");
const assetsDir = path.join(rootDir, "database/assets/demo-products");
const storageDir = path.join(rootDir, "storage/app/public/demo-products");

/** @type {Array<{ file: string; url: string; credit: string }>} */
const demos = [
  {
    file: "phone.jpg",
    url: "https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — smartphone product photo",
  },
  {
    file: "laptop.jpg",
    url: "https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — laptop on desk",
  },
  {
    file: "shoes.jpg",
    url: "https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — sneakers",
  },
  {
    file: "bag.jpg",
    url: "https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — handbag",
  },
  {
    file: "dress.jpg",
    url: "https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — dress",
  },
  {
    file: "watch.jpg",
    url: "https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — wristwatch",
  },
  {
    file: "perfume.jpg",
    url: "https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — perfume bottle",
  },
  {
    file: "chair.jpg",
    url: "https://images.unsplash.com/photo-1506439773649-6e0eb8cfb237?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — chair",
  },
  {
    file: "table.jpg",
    url: "https://images.unsplash.com/photo-1532372320572-cda25653a26d?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — table",
  },
  {
    file: "headphones.jpg",
    url: "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1200&h=1200&q=85",
    credit: "Unsplash — headphones",
  },
];

async function downloadImage(url) {
  const response = await fetch(url, {
    headers: {
      Accept: "image/jpeg,image/*",
      "User-Agent": "china-order-tz-demo-asset-generator/1.0",
    },
    redirect: "follow",
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status} for ${url}`);
  }

  const contentType = response.headers.get("content-type") ?? "";

  if (!contentType.includes("image")) {
    throw new Error(`Unexpected content-type "${contentType}" for ${url}`);
  }

  return Buffer.from(await response.arrayBuffer());
}

async function main() {
  fs.mkdirSync(assetsDir, { recursive: true });
  fs.mkdirSync(storageDir, { recursive: true });

  const credits = [];

  for (const demo of demos) {
    const buffer = await downloadImage(demo.url);
    const assetPath = path.join(assetsDir, demo.file);
    const storagePath = path.join(storageDir, demo.file);

    fs.writeFileSync(assetPath, buffer);
    fs.writeFileSync(storagePath, buffer);

    credits.push(`${demo.file}: ${demo.credit}`);
    console.log(`Downloaded ${demo.file} (${buffer.length} bytes)`);
  }

  fs.writeFileSync(
    path.join(assetsDir, "CREDITS.txt"),
    `${credits.join("\n")}\n\nSource: https://unsplash.com/license\n`,
    "utf8",
  );

  console.log(`\nWrote ${demos.length} images to:\n  ${assetsDir}\n  ${storageDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
