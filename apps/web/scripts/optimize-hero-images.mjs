import sharp from "sharp";
import { mkdir } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const assetsDir =
  process.env.HERO_SOURCE_ASSETS_DIR ??
  path.join(process.env.USERPROFILE ?? process.env.HOME ?? "", ".cursor/projects/c-Users-hp-Desktop-china-oder-tz/assets");
const outDir = path.join(__dirname, "../public/images/hero");

const jobs = [
  {
    input: "order-from-china-desktop.png",
    output: "order-from-china-desktop.webp",
    width: 2560,
    height: 1200,
    quality: 82,
  },
  {
    input: "order-from-china-mobile.png",
    output: "order-from-china-mobile.webp",
    width: 1080,
    height: 1440,
    quality: 80,
  },
  {
    input: "buy-from-tz-desktop.png",
    output: "buy-from-tz-desktop.webp",
    width: 2560,
    height: 1200,
    quality: 82,
  },
  {
    input: "buy-from-tz-mobile.png",
    output: "buy-from-tz-mobile.webp",
    width: 1080,
    height: 1440,
    quality: 80,
  },
];

await mkdir(outDir, { recursive: true });

for (const job of jobs) {
  const inputPath = path.join(assetsDir, job.input);
  const outputPath = path.join(outDir, job.output);

  await sharp(inputPath)
    .resize(job.width, job.height, { fit: "cover", position: "centre" })
    .webp({ quality: job.quality, effort: 4 })
    .toFile(outputPath);

  const stats = await sharp(outputPath).metadata();
  console.log(`Wrote ${job.output} (${stats.width}x${stats.height})`);
}
