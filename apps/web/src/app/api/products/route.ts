import { NextResponse } from "next/server";
import type { Product } from "@/lib/types/catalog";
import { SEED_PRODUCTS } from "@/lib/catalog/seed-products";
import {
  isServerProductsInitialized,
  readServerProducts,
  writeServerProducts,
} from "@/lib/services/product-storage-server";

export async function GET() {
  const stored = await readServerProducts();
  const products = stored ?? SEED_PRODUCTS;
  return NextResponse.json(products);
}

export async function PUT(request: Request) {
  const body = (await request.json()) as { products?: Product[] };
  const products = body.products;

  if (!Array.isArray(products)) {
    return NextResponse.json({ error: "Invalid products payload" }, { status: 400 });
  }

  await writeServerProducts(products);
  return NextResponse.json({ ok: true });
}

export async function HEAD() {
  const initialized = await isServerProductsInitialized();
  return new NextResponse(null, { status: initialized ? 200 : 204 });
}
