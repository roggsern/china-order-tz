const sharp = require('sharp');
const path = require('path');

/**
 * Android small notification icon (Expo SDK 57):
 * - 96x96 PNG
 * - opaque white (#FFFFFF) foreground only
 * - fully transparent background
 * - simplified CHINA ORDER TZ shopping-cart + arc silhouette
 *
 * Android tints by alpha mask; color comes from plugin `color` (#c9a227).
 */
const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">
  <g fill="#FFFFFF">
    <!-- Brand arc (right) — simplified from gold circular cart frame -->
    <path d="M58 20
      a28 28 0 0 1 0 56
      a8 8 0 0 0 0-16
      a12 12 0 0 0 0-24
      a8 8 0 0 0 0-16z"/>

    <!-- Cart handle (left upright + top bar) -->
    <rect x="18" y="28" width="8" height="34" rx="2"/>
    <rect x="12" y="28" width="20" height="7" rx="2"/>

    <!-- Motion streaks -->
    <rect x="6" y="38" width="10" height="4" rx="2"/>
    <rect x="4" y="46" width="12" height="4" rx="2"/>
    <rect x="6" y="54" width="10" height="4" rx="2"/>

    <!-- Basket (angled via trapezoid path) -->
    <path d="M28 38 h34 l-5 22 H34 z"/>

    <!-- Basket rim -->
    <rect x="28" y="34" width="36" height="6" rx="1"/>

    <!-- Wheels -->
    <circle cx="38" cy="72" r="6"/>
    <circle cx="58" cy="72" r="6"/>
  </g>
</svg>`;

const out = path.join(__dirname, '..', 'assets', 'images', 'notification-icon.png');

async function main() {
  await sharp(Buffer.from(svg)).resize(96, 96).ensureAlpha().png().toFile(out);

  const meta = await sharp(out).metadata();
  const { data, info } = await sharp(out)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  let opaque = 0;
  let transparent = 0;
  let whiteOpaque = 0;
  let nonWhiteOpaque = 0;
  let semiTransparent = 0;

  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    const a = data[i + 3];
    if (a === 0) transparent += 1;
    else if (a < 255) {
      semiTransparent += 1;
      opaque += 1;
    } else {
      opaque += 1;
      if (r === 255 && g === 255 && b === 255) whiteOpaque += 1;
      else nonWhiteOpaque += 1;
    }
  }

  console.log(
    JSON.stringify(
      {
        out,
        meta: {
          width: meta.width,
          height: meta.height,
          channels: meta.channels,
          hasAlpha: meta.hasAlpha,
          format: meta.format,
        },
        pixels: {
          opaque,
          transparent,
          whiteOpaque,
          nonWhiteOpaque,
          semiTransparent,
          total: info.width * info.height,
        },
      },
      null,
      2,
    ),
  );
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
