import { readFileSync, writeFileSync, mkdirSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";
import pngToIco from "png-to-ico";

const __dirname = dirname(fileURLToPath(import.meta.url));
const publicDir = join(__dirname, "..", "public");
const brandingDir = join(publicDir, "branding");

const BRANDING_FILES = [
  "logo-header.png",
  "logo-footer.png",
  "logo-branding.png",
  "favicon.png",
  "apple-touch-icon.png",
  "icon-192.png",
  "icon-512.png",
];

async function main() {
  mkdirSync(brandingDir, { recursive: true });

  const faviconSource = join(brandingDir, "favicon.png");
  if (!existsSync(faviconSource)) {
    console.error("Missing /branding/favicon.png — run extract-branding first.");
    process.exit(1);
  }

  const favicon = readFileSync(faviconSource);
  const sizes = [16, 32, 48];
  const pngBuffers = {};

  for (const size of sizes) {
    pngBuffers[size] = await sharp(favicon).resize(size, size).png().toBuffer();
  }

  const icoBuffer = await pngToIco([
    pngBuffers[16],
    pngBuffers[32],
    pngBuffers[48],
  ]);
  writeFileSync(join(publicDir, "favicon.ico"), icoBuffer);

  const missing = BRANDING_FILES.filter((file) => !existsSync(join(brandingDir, file)));
  if (missing.length > 0) {
    console.warn(
      `Missing official branding PNGs in public/branding/: ${missing.join(", ")}`,
    );
  }

  console.log("Generated favicon.ico from /branding/favicon.png");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
