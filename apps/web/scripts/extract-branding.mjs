/**
 * Extract official logo PNGs from the branding board.
 *
 * Usage:
 *   node scripts/extract-branding.mjs [path/to/branding-board.png]
 *
 * Default source: public/branding/branding-board.png
 */
import { existsSync, mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const __dirname = dirname(fileURLToPath(import.meta.url));
const publicDir = join(__dirname, "..", "public");
const brandingDir = join(publicDir, "branding");

/** Crop regions as fractions of source dimensions (left, top, width, height). */
const BRAND_CROPS = {
  header: {
    left: 0.025,
    top: 0.505,
    width: 0.31,
    height: 0.092,
    out: "logo-header.png",
    removeWhite: true,
  },
  footer: {
    left: 0.525,
    top: 0.505,
    width: 0.24,
    height: 0.11,
    out: "logo-footer.png",
    removeWhite: false,
  },
  favicon: {
    left: 0.275,
    top: 0.718,
    width: 0.095,
    height: 0.11,
    out: "favicon.png",
    removeWhite: false,
  },
  appleTouchIcon: {
    left: 0.275,
    top: 0.718,
    width: 0.095,
    height: 0.11,
    out: "apple-touch-icon.png",
    removeWhite: false,
  },
  icon512: {
    left: 0.405,
    top: 0.702,
    width: 0.125,
    height: 0.135,
    out: "icon-512.png",
    removeWhite: false,
  },
  branding: {
    left: 0.52,
    top: 0.06,
    width: 0.46,
    height: 0.28,
    out: "logo-branding.png",
    removeWhite: false,
  },
};

const WHITE_THRESHOLD = 248;

async function removeWhiteBackground(inputBuffer) {
  const { data, info } = await sharp(inputBuffer)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    if (r >= WHITE_THRESHOLD && g >= WHITE_THRESHOLD && b >= WHITE_THRESHOLD) {
      data[i + 3] = 0;
    }
  }

  return sharp(data, {
    raw: { width: info.width, height: info.height, channels: 4 },
  })
    .trim({ threshold: 10 })
    .png()
    .toBuffer();
}

async function extractVariant(sourcePath, meta, crop, outPath) {
  const left = Math.round(meta.width * crop.left);
  const top = Math.round(meta.height * crop.top);
  const width = Math.round(meta.width * crop.width);
  const height = Math.round(meta.height * crop.height);

  const cropped = await sharp(sourcePath)
    .extract({ left, top, width, height })
    .png()
    .toBuffer();

  const processed = crop.removeWhite
    ? await removeWhiteBackground(cropped)
    : await sharp(cropped).trim({ threshold: 10 }).png().toBuffer();

  await sharp(processed).png().toFile(outPath);
  console.log(`  ✓ ${crop.out}`);
}

async function main() {
  const sourceArg = process.argv[2];
  const sourcePath = sourceArg
    ? sourceArg
    : join(brandingDir, "branding-board.png");

  if (!existsSync(sourcePath)) {
    console.error(`Branding board not found: ${sourcePath}`);
    process.exit(1);
  }

  mkdirSync(brandingDir, { recursive: true });

  const meta = await sharp(sourcePath).metadata();
  if (!meta.width || !meta.height) {
    throw new Error("Could not read branding board dimensions");
  }

  console.log(`Extracting from ${sourcePath} (${meta.width}×${meta.height})`);

  for (const crop of Object.values(BRAND_CROPS)) {
    await extractVariant(sourcePath, meta, crop, join(brandingDir, crop.out));
  }

  const faviconPath = join(brandingDir, "favicon.png");
  await sharp(faviconPath)
    .resize(192, 192, { fit: "contain", background: { r: 0, g: 0, b: 0, alpha: 1 } })
    .png()
    .toFile(join(brandingDir, "icon-192.png"));
  console.log("  ✓ icon-192.png (resized from favicon.png)");

  console.log("\nDone. Run: npm run generate-icons");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
