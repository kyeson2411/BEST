// Builds the web versions of the school photo and seal from the originals in tools/school-assets/:
//   campus.jpg  the landing-page photograph (any size, landscape)
//   seal.jpg    the school seal on a plain white background (square)
// Run after replacing either file:  node tools/school-assets.mjs
//
// Output (public/assets/school/): campus-{800,1280,2000}.webp plus campus-1280.jpg, the seal with a
// transparent background as seal-{384,192}.webp and seal-{192,96,48}.png, favicon-32.png and apple-touch-icon.png,
// and the installed app's icons app-icon-{192,512}.png and app-icon-maskable-{192,512}.png (the seal inside the
// central circle that phones keep when they crop icons). Re-encoding also drops any camera or
// location metadata from the originals. Uses the installed Microsoft Edge (or SIBOL_BROWSER=chromium).
import { chromium } from '@playwright/test';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const src = 'tools/school-assets';
const out = 'public/assets/school';
mkdirSync(out, { recursive: true });

const channel = process.env.SIBOL_BROWSER || 'msedge';
const browser = await chromium.launch(channel === 'chromium' ? {} : { channel });
const page = await browser.newPage();
await page.setContent('<!doctype html><title>assets</title>');

const asDataUrl = (file) => 'data:image/jpeg;base64,' + readFileSync(`${src}/${file}`).toString('base64');
const save = (name, dataUrl) => {
  const bytes = Buffer.from(dataUrl.split(',')[1], 'base64');
  writeFileSync(`${out}/${name}`, bytes);
  console.log(`${name.padEnd(24)} ${(bytes.length / 1024).toFixed(0).padStart(5)} KB`);
};

// Photograph: three widths, WebP with a JPEG fallback.
const photo = asDataUrl('campus.jpg');
for (const width of [800, 1280, 2000]) {
  const result = await page.evaluate(async ({ photo, width }) => {
    const img = new Image();
    img.src = photo;
    await img.decode();
    const w = Math.min(width, img.naturalWidth);
    const h = Math.round((img.naturalHeight * w) / img.naturalWidth);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, 0, 0, w, h);
    return { jpg: canvas.toDataURL('image/jpeg', 0.8), webp: canvas.toDataURL('image/webp', 0.76), w, h };
  }, { photo, width });
  save(`campus-${result.w}.webp`, result.webp);
  if (width === 1280) save(`campus-${result.w}.jpg`, result.jpg); // fallback for browsers without WebP
}

// Seal: remove the white background connected to the image border (the white inside the seal stays),
// soften the anti-aliased rim, crop to the seal, and export several sizes.
const seal = await page.evaluate(async (dataUrl) => {
  const img = new Image();
  img.src = dataUrl;
  await img.decode();
  const W = img.naturalWidth;
  const H = img.naturalHeight;
  const canvas = document.createElement('canvas');
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(img, 0, 0);
  const image = ctx.getImageData(0, 0, W, H);
  const px = image.data;
  const minC = (i) => Math.min(px[i], px[i + 1], px[i + 2]);
  const background = new Uint8Array(W * H);
  const queue = new Int32Array(W * H);
  let head = 0;
  let tail = 0;
  const visit = (p) => {
    if (!background[p] && minC(p * 4) >= 200) {
      background[p] = 1;
      queue[tail++] = p;
    }
  };
  for (let x = 0; x < W; x++) { visit(x); visit((H - 1) * W + x); }
  for (let y = 0; y < H; y++) { visit(y * W); visit(y * W + W - 1); }
  while (head < tail) {
    const p = queue[head++];
    const x = p % W;
    const y = (p - x) / W;
    if (x > 0) visit(p - 1);
    if (x < W - 1) visit(p + 1);
    if (y > 0) visit(p - W);
    if (y < H - 1) visit(p + W);
  }
  // Rim pixels next to the background are blends of white and the seal's green: estimate coverage
  // from how far the darkest channel is from white, then un-blend the colour.
  const rim = new Uint8Array(W * H);
  for (let p = 0; p < W * H; p++) {
    if (background[p]) continue;
    const x = p % W;
    const y = (p - x) / W;
    let edge = false;
    for (let dy = -2; dy <= 2 && !edge; dy++) {
      for (let dx = -2; dx <= 2 && !edge; dx++) {
        const nx = x + dx;
        const ny = y + dy;
        if (nx >= 0 && ny >= 0 && nx < W && ny < H && background[ny * W + nx]) edge = true;
      }
    }
    if (edge) rim[p] = 1;
  }
  for (let p = 0; p < W * H; p++) {
    const i = p * 4;
    if (background[p]) { px[i + 3] = 0; continue; }
    if (!rim[p]) continue;
    const a = Math.max(0, Math.min(1, (255 - minC(i)) / 215));
    if (a < 0.04) { px[i + 3] = 0; continue; }
    for (let c = 0; c < 3; c++) px[i + c] = Math.max(0, Math.min(255, Math.round((px[i + c] - (1 - a) * 255) / a)));
    px[i + 3] = Math.round(a * 255);
  }
  // Drop specks outside the seal: keep only pixels within the largest circle centred on the opaque area.
  let minX = W; let minY = H; let maxX = 0; let maxY = 0;
  for (let p = 0; p < W * H; p++) {
    if (px[p * 4 + 3] < 128) continue;
    const x = p % W;
    const y = (p - x) / W;
    // ignore isolated dust: require an opaque neighbour run
    if (x > 2 && px[(p - 2) * 4 + 3] < 128) continue;
    minX = Math.min(minX, x); maxX = Math.max(maxX, x); minY = Math.min(minY, y); maxY = Math.max(maxY, y);
  }
  const cx = (minX + maxX) / 2;
  const cy = (minY + maxY) / 2;
  const r = Math.max(maxX - minX, maxY - minY) / 2 + 2;
  for (let p = 0; p < W * H; p++) {
    const x = p % W;
    const y = (p - x) / W;
    if ((x - cx) ** 2 + (y - cy) ** 2 > r * r) px[p * 4 + 3] = 0;
  }
  ctx.putImageData(image, 0, 0);
  const size = Math.ceil(r * 2) + 8;
  const crop = document.createElement('canvas');
  crop.width = size;
  crop.height = size;
  crop.getContext('2d').drawImage(canvas, Math.round(cx - size / 2), Math.round(cy - size / 2), size, size, 0, 0, size, size);

  const scaled = (s, background, padding = 0.08) => {
    const c = document.createElement('canvas');
    c.width = s;
    c.height = s;
    const g = c.getContext('2d');
    g.imageSmoothingQuality = 'high';
    if (background) {
      g.fillStyle = background;
      g.fillRect(0, 0, s, s);
      const pad = Math.round(s * padding);
      g.drawImage(crop, pad, pad, s - pad * 2, s - pad * 2);
    } else {
      // Step down in halves for a cleaner small image.
      let current = crop;
      while (current.width / 2 > s) {
        const half = document.createElement('canvas');
        half.width = Math.round(current.width / 2);
        half.height = Math.round(current.height / 2);
        const hg = half.getContext('2d');
        hg.imageSmoothingQuality = 'high';
        hg.drawImage(current, 0, 0, half.width, half.height);
        current = half;
      }
      g.drawImage(current, 0, 0, s, s);
    }
    return { png: c.toDataURL('image/png'), webp: c.toDataURL('image/webp', 0.86) };
  };
  return {
    box: { cx, cy, r, size },
    s384: scaled(384), s192: scaled(192), s96: scaled(96), s48: scaled(48), s32: scaled(32),
    touch: scaled(180, '#F6F5EE'),
    // Maskable icons keep only a central circle 80% wide; 14% padding keeps the round seal inside it.
    app192: scaled(192, '#F6F5EE'), app512: scaled(512, '#F6F5EE'),
    maskable192: scaled(192, '#F6F5EE', 0.14), maskable512: scaled(512, '#F6F5EE', 0.14),
  };
}, asDataUrl('seal.jpg'));

console.log('seal found at', JSON.stringify(seal.box));
save('seal-384.webp', seal.s384.webp);
save('seal-192.webp', seal.s192.webp);
save('seal-192.png', seal.s192.png);
save('seal-96.png', seal.s96.png);
save('seal-48.png', seal.s48.png);
save('favicon-32.png', seal.s32.png);
save('apple-touch-icon.png', seal.touch.png);
save('app-icon-192.png', seal.app192.png);
save('app-icon-512.png', seal.app512.png);
save('app-icon-maskable-192.png', seal.maskable192.png);
save('app-icon-maskable-512.png', seal.maskable512.png);
await browser.close();
