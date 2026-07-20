/**
 * Creates transparent PNG variants of dark-background logo assets for premium dark UI.
 *
 * Usage: node scripts/generate-transparent-logos.mjs
 */
import { existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const __dirname = dirname(fileURLToPath(import.meta.url));
const brandingDir = join(__dirname, "..", "public", "branding");

const BLACK_THRESHOLD = 18;

const SOURCES = [
  { input: "logo-footer.png", output: "logo-footer-transparent.png" },
  { input: "logo-branding.png", output: "logo-branding-transparent.png" },
];

async function removeNearBlackBackground(inputBuffer) {
  const { data, info } = await sharp(inputBuffer)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];

    if (r <= BLACK_THRESHOLD && g <= BLACK_THRESHOLD && b <= BLACK_THRESHOLD) {
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

async function main() {
  for (const { input, output } of SOURCES) {
    const inputPath = join(brandingDir, input);
    const outputPath = join(brandingDir, output);

    if (!existsSync(inputPath)) {
      console.error(`Missing source asset: ${inputPath}`);
      process.exit(1);
    }

    const source = await sharp(inputPath).png().toBuffer();
    const transparent = await removeNearBlackBackground(source);
    await sharp(transparent).png().toFile(outputPath);
    console.log(`  ✓ ${output}`);
  }

  console.log("\nTransparent logo assets ready.");
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
