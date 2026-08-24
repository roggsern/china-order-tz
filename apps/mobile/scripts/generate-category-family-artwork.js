const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

/**
 * Rasterize fallback Shop-by-Category family still-lifes (1024×1024, no text/logos).
 * Photorealistic masters in `assets/images/categories/` are preferred.
 * This script overwrites those files — run only when regenerating SVG fallbacks.
 *
 * Usage: node scripts/generate-category-family-artwork.js
 */

const OUT_DIR = path.join(__dirname, '..', 'assets', 'images', 'categories');

function studio(inner, light, dark) {
  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">
  <defs>
    <radialGradient id="bg" cx="46%" cy="34%" r="74%">
      <stop offset="0%" stop-color="${light}"/>
      <stop offset="100%" stop-color="${dark}"/>
    </radialGradient>
    <radialGradient id="floor" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#2c2824" stop-opacity="0.22"/>
      <stop offset="100%" stop-color="#2c2824" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="1024" height="1024" fill="url(#bg)"/>
  <ellipse cx="512" cy="792" rx="310" ry="54" fill="url(#floor)"/>
  ${inner}
</svg>`;
}

const SVG = {
  automotive: studio(
    `
    <!-- spare tire (scale-matched) -->
    <ellipse cx="220" cy="655" rx="58" ry="80" fill="#1c1c1c"/>
    <ellipse cx="220" cy="655" rx="38" ry="54" fill="#4a4a4a"/>
    <ellipse cx="220" cy="655" rx="16" ry="22" fill="#d7d1c8"/>
    <!-- sedan -->
    <path d="M310 618
      C328 555 372 522 430 518
      L545 518
      C610 455 720 448 805 498
      L888 538
      C908 548 922 578 922 608
      L922 668
      C922 686 908 700 890 700
      L812 700
      C802 662 768 638 732 638
      C696 638 662 662 652 700
      L500 700
      C490 662 456 638 420 638
      C384 638 350 662 340 700
      L308 700
      C294 700 284 688 284 674
      L284 646
      C284 632 296 620 310 618Z" fill="#2c3138"/>
    <path d="M548 522 L708 522 C722 488 768 472 822 502 L868 530 L718 530 C662 488 586 498 548 522Z" fill="#a9c0ce"/>
    <circle cx="420" cy="698" r="46" fill="#161616"/>
    <circle cx="420" cy="698" r="24" fill="#d9d3cb"/>
    <circle cx="420" cy="698" r="8" fill="#2a2a2a"/>
    <circle cx="732" cy="698" r="46" fill="#161616"/>
    <circle cx="732" cy="698" r="24" fill="#d9d3cb"/>
    <circle cx="732" cy="698" r="8" fill="#2a2a2a"/>
    <ellipse cx="900" cy="588" rx="14" ry="10" fill="#f4e6c0"/>
    `,
    '#F3EEE8',
    '#D4CFC8',
  ),

  'health-medical': studio(
    `
    <!-- BP monitor -->
    <rect x="560" y="430" width="230" height="170" rx="22" fill="#eef3f7" stroke="#c5d0d8" stroke-width="6"/>
    <rect x="590" y="458" width="170" height="88" rx="10" fill="#1f3a4a"/>
    <circle cx="640" cy="572" r="10" fill="#3d8f7a"/>
    <circle cx="720" cy="572" r="10" fill="#c5d0d8"/>
    <!-- cuff -->
    <rect x="600" y="620" width="150" height="36" rx="12" fill="#4a6a7a"/>
    <!-- stethoscope -->
    <path d="M250 360 C250 300 290 270 340 270 C390 270 430 300 430 360" fill="none" stroke="#2c5f73" stroke-width="16" stroke-linecap="round"/>
    <circle cx="250" cy="360" r="16" fill="#d9e3ea"/>
    <circle cx="430" cy="360" r="16" fill="#d9e3ea"/>
    <path d="M340 270 C340 270 340 430 340 500 C340 560 280 610 230 640" fill="none" stroke="#2c5f73" stroke-width="18" stroke-linecap="round"/>
    <circle cx="214" cy="668" r="54" fill="#dfe8ee" stroke="#2c5f73" stroke-width="10"/>
    <circle cx="214" cy="668" r="22" fill="#2c5f73"/>
    <!-- thermometer -->
    <rect x="470" y="500" width="28" height="210" rx="14" fill="#f7fbfd" stroke="#b7c6d0" stroke-width="4"/>
    <circle cx="484" cy="720" r="28" fill="#c45c5c"/>
    <rect x="478" y="560" width="12" height="150" rx="6" fill="#c45c5c"/>
    `,
    '#F5F8FA',
    '#D5DEE6',
  ),

  'phones-tablets': studio(
    `
    <rect x="250" y="300" width="280" height="430" rx="28" fill="#2b2e33"/>
    <rect x="268" y="330" width="244" height="370" rx="12" fill="#111318"/>
    <circle cx="390" cy="710" r="10" fill="#4a4e55"/>
    <rect x="470" y="250" width="310" height="430" rx="24" fill="#3a3f46"/>
    <rect x="488" y="278" width="274" height="374" rx="10" fill="#171a20"/>
    <rect x="620" y="258" width="40" height="8" rx="4" fill="#5a5f66"/>
    `,
    '#F2F3F5',
    '#D5D8DE',
  ),

  'computers-office': studio(
    `
    <!-- monitor -->
    <rect x="250" y="250" width="420" height="280" rx="16" fill="#2c3036"/>
    <rect x="268" y="268" width="384" height="244" rx="6" fill="#12151a"/>
    <rect x="430" y="530" width="60" height="36" fill="#c8c2ba"/>
    <rect x="340" y="566" width="240" height="18" rx="4" fill="#b7b1a8"/>
    <!-- laptop -->
    <path d="M430 620 L860 620 L820 740 L390 740 Z" fill="#d9d4cc"/>
    <rect x="470" y="430" width="340" height="190" rx="10" fill="#2a2e34"/>
    <rect x="486" y="446" width="308" height="158" rx="4" fill="#0f1216"/>
    `,
    '#F3F1ED',
    '#D8D3CB',
  ),

  'home-appliances': studio(
    `
    <!-- kettle -->
    <ellipse cx="340" cy="560" rx="110" ry="28" fill="#d7d2cb"/>
    <path d="M250 430 C250 360 290 320 340 320 C390 320 430 360 430 430 L430 560 L250 560 Z" fill="#ece8e2"/>
    <path d="M430 400 C490 400 500 460 470 500" fill="none" stroke="#c9c3bb" stroke-width="18" stroke-linecap="round"/>
    <rect x="318" y="292" width="44" height="28" rx="8" fill="#c45c5c"/>
    <!-- blender -->
    <rect x="560" y="560" width="200" height="90" rx="16" fill="#2f3338"/>
    <path d="M590 300 L730 300 L750 560 L570 560 Z" fill="#c5d8e6" opacity="0.85"/>
    <rect x="620" y="250" width="80" height="50" rx="10" fill="#ece8e2"/>
    `,
    '#F6F1EA',
    '#E0D6C9',
  ),

  'professional-audio': studio(
    `
    <!-- mixer -->
    <rect x="180" y="560" width="360" height="170" rx="16" fill="#2a2d32"/>
    <circle cx="240" cy="620" r="16" fill="#c9a227"/>
    <circle cx="300" cy="620" r="16" fill="#d0cbc3"/>
    <circle cx="360" cy="620" r="16" fill="#d0cbc3"/>
    <circle cx="420" cy="620" r="16" fill="#d0cbc3"/>
    <rect x="220" y="660" width="280" height="36" rx="6" fill="#1a1d21"/>
    <!-- mic -->
    <ellipse cx="640" cy="340" rx="70" ry="110" fill="#cfc8bf"/>
    <ellipse cx="640" cy="340" rx="46" ry="80" fill="#8a837a"/>
    <rect x="624" y="440" width="32" height="160" rx="8" fill="#3a3a3a"/>
    <circle cx="640" cy="620" r="28" fill="#2a2a2a"/>
    <!-- headphones -->
    <path d="M760 420 C860 420 900 500 900 560" fill="none" stroke="#2a2a2a" stroke-width="18" stroke-linecap="round"/>
    <rect x="868" y="540" width="54" height="90" rx="16" fill="#1a1a1a"/>
    `,
    '#F1EEEA',
    '#D3CEC8',
  ),

  'jewelry-watches': studio(
    `
    <!-- necklace -->
    <ellipse cx="400" cy="430" rx="160" ry="210" fill="none" stroke="#d4b45a" stroke-width="10"/>
    <circle cx="400" cy="640" r="28" fill="#e0c36a"/>
    <!-- rings -->
    <ellipse cx="620" cy="620" rx="40" ry="18" fill="none" stroke="#d4b45a" stroke-width="10"/>
    <ellipse cx="680" cy="640" rx="36" ry="16" fill="none" stroke="#c9c3bb" stroke-width="8"/>
    <!-- watch -->
    <rect x="700" y="360" width="90" height="150" rx="28" fill="#2a2a2a"/>
    <circle cx="745" cy="435" r="44" fill="#f4efe6" stroke="#d4b45a" stroke-width="6"/>
    <line x1="745" y1="435" x2="745" y2="408" stroke="#2a2a2a" stroke-width="4"/>
    <line x1="745" y1="435" x2="768" y2="448" stroke="#2a2a2a" stroke-width="3"/>
    `,
    '#F7F2EA',
    '#E4D7C4',
  ),

  'sports-outdoors': studio(
    `
    <!-- soccer ball -->
    <circle cx="360" cy="520" r="150" fill="#f5f5f5" stroke="#2a2a2a" stroke-width="8"/>
    <polygon points="360,430 400,455 385,500 335,500 320,455" fill="#2a2a2a"/>
    <polygon points="430,500 470,530 445,575 400,560 405,515" fill="#2a2a2a"/>
    <polygon points="290,500 315,515 320,560 275,575 250,530" fill="#2a2a2a"/>
    <!-- dumbbell -->
    <rect x="560" y="620" width="260" height="36" rx="10" fill="#6b7280"/>
    <rect x="540" y="590" width="50" height="96" rx="8" fill="#1f2937"/>
    <rect x="790" y="590" width="50" height="96" rx="8" fill="#1f2937"/>
    <!-- shoe silhouette -->
    <path d="M560 500 C620 470 740 470 800 510 L820 540 C780 560 640 570 560 540 Z" fill="#c45c5c"/>
    `,
    '#F4F1EB',
    '#D9D3C8',
  ),

  'industrial-tools': studio(
    `
    <!-- toolbox -->
    <rect x="180" y="520" width="280" height="170" rx="12" fill="#c45c5c"/>
    <rect x="180" y="500" width="280" height="40" rx="10" fill="#9a3f3f"/>
    <rect x="290" y="470" width="60" height="40" rx="8" fill="#d9d3cb"/>
    <!-- drill -->
    <rect x="520" y="430" width="220" height="90" rx="20" fill="#2f3338"/>
    <rect x="730" y="452" width="140" height="46" rx="8" fill="#6b7280"/>
    <rect x="860" y="462" width="70" height="26" rx="6" fill="#cfc8bf"/>
    <rect x="560" y="390" width="50" height="50" rx="8" fill="#c45c5c"/>
    <!-- wrench -->
    <path d="M240 320 C240 280 280 260 320 280 C340 290 340 310 320 330 L480 490" fill="none" stroke="#cfc8bf" stroke-width="28" stroke-linecap="round"/>
    <circle cx="250" cy="300" r="34" fill="none" stroke="#cfc8bf" stroke-width="22"/>
    `,
    '#F2EFEA',
    '#D6D0C7',
  ),

  'pet-supplies': studio(
    `
    <!-- bowl -->
    <ellipse cx="380" cy="640" rx="180" ry="50" fill="#c9c3bb"/>
    <path d="M220 520 C220 480 280 450 380 450 C480 450 540 480 540 520 L510 640 L250 640 Z" fill="#e8e2da"/>
    <ellipse cx="380" cy="520" rx="140" ry="36" fill="#d9cbb8"/>
    <!-- toy ball -->
    <circle cx="700" cy="560" r="70" fill="#d97746"/>
    <circle cx="700" cy="560" r="28" fill="#f3e6c4"/>
    <!-- leash coil -->
    <path d="M620 380 C700 340 820 380 800 460 C780 520 680 500 700 430" fill="none" stroke="#2f3338" stroke-width="16" stroke-linecap="round"/>
    `,
    '#F6F1E9',
    '#E2D6C6',
  ),

  groceries: studio(
    `
    <!-- produce -->
    <circle cx="340" cy="520" r="90" fill="#c45c5c"/>
    <ellipse cx="340" cy="500" rx="70" ry="54" fill="#d97878"/>
    <path d="M340 430 C350 400 380 400 370 430" fill="none" stroke="#3d6b3d" stroke-width="10" stroke-linecap="round"/>
    <circle cx="470" cy="560" r="70" fill="#e09a3a"/>
    <circle cx="250" cy="600" r="54" fill="#6b8f4e"/>
    <!-- oil bottle -->
    <rect x="620" y="360" width="110" height="280" rx="24" fill="#e8d7a8" opacity="0.92"/>
    <rect x="650" y="310" width="50" height="60" rx="10" fill="#d9d3cb"/>
    <ellipse cx="675" cy="640" rx="70" ry="20" fill="#c9c3bb"/>
    <!-- grain sack -->
    <path d="M760 480 L900 480 L920 700 L740 700 Z" fill="#e6dcc8"/>
    <path d="M760 480 L900 480 L890 510 L770 510 Z" fill="#d4c6a8"/>
    `,
    '#F7F2EA',
    '#E3D7C4',
  ),
};

async function main() {
  if (!process.argv.includes('--force')) {
    console.error(
      'Refusing to overwrite photorealistic category masters.\n' +
        'Pass --force to rasterize SVG fallbacks into assets/images/categories/.',
    );
    process.exit(1);
  }
  fs.mkdirSync(OUT_DIR, { recursive: true });
  for (const [key, svg] of Object.entries(SVG)) {
    const out = path.join(OUT_DIR, `${key}.png`);
    await sharp(Buffer.from(svg)).resize(1024, 1024).png({ compressionLevel: 9 }).toFile(out);
    const meta = await sharp(out).metadata();
    if (meta.width !== 1024 || meta.height !== 1024) {
      throw new Error(`${key} rendered at ${meta.width}x${meta.height}`);
    }
    process.stdout.write(`wrote ${path.relative(process.cwd(), out)}\n`);
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
