const sharp = require('sharp');
const path = require('path');

/**
 * Derive a splash-safe lockup from the canonical splash-brand.png.
 * Does not overwrite the canonical logo. Does not redraw artwork.
 *
 * Android 12+ native splash draws the image in a circular icon. A 3.7:1
 * wordmark with zero right/top padding therefore clips “TZ” and the tagline.
 * This script centers the existing lockup on a square canvas so the full
 * artwork stays inside that circle when resizeMode is contain.
 *
 * Usage: node scripts/generate-splash-brand-safe.js
 */

const SRC = path.join(__dirname, '..', 'assets', 'branding', 'splash-brand.png');
const OUT = path.join(__dirname, '..', 'assets', 'branding', 'splash-brand-safe.png');

const CANVAS = 2048;
/** Lockup width as a fraction of the square — stays inside the ~66% icon safe circle. */
const LOCKUP_WIDTH_RATIO = 0.58;

async function main() {
  const src = sharp(SRC);
  const meta = await src.metadata();
  if (!meta.width || !meta.height) {
    throw new Error('splash-brand.png has no dimensions');
  }

  const targetWidth = Math.round(CANVAS * LOCKUP_WIDTH_RATIO);
  const targetHeight = Math.round((meta.height / meta.width) * targetWidth);
  const left = Math.round((CANVAS - targetWidth) / 2);
  const top = Math.round((CANVAS - targetHeight) / 2);

  const rawLockup = await sharp(SRC)
    .resize(targetWidth, targetHeight, { fit: 'fill' })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const { data, info } = rawLockup;
  for (let i = 0; i < data.length; i += 4) {
    if (data[i] >= 248 && data[i + 1] >= 248 && data[i + 2] >= 248) {
      data[i + 3] = 0;
    }
  }

  const lockup = await sharp(data, {
    raw: { width: info.width, height: info.height, channels: 4 },
  })
    .png()
    .toBuffer();

  await sharp({
    create: {
      width: CANVAS,
      height: CANVAS,
      channels: 4,
      background: { r: 255, g: 248, b: 234, alpha: 0 },
    },
  })
    .composite([{ input: lockup, left, top }])
    .png({ compressionLevel: 9 })
    .toFile(OUT);

  const outMeta = await sharp(OUT).metadata();
  if (outMeta.width !== CANVAS || outMeta.height !== CANVAS) {
    throw new Error(`expected ${CANVAS}x${CANVAS}, got ${outMeta.width}x${outMeta.height}`);
  }

  process.stdout.write(
    JSON.stringify(
      {
        src: { width: meta.width, height: meta.height },
        out: { width: outMeta.width, height: outMeta.height, left, top, targetWidth, targetHeight },
      },
      null,
      2,
    ) + '\n',
  );
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
