// Teaser vidéo terricom (MP4 1920×1080, 30 i/s) : images calculées depuis docs/teaser/clip.html, musique
// docs/teaser/musique.wav (scripts/teaser_musique.py). Nécessite un ffmpeg avec libx264 (variable FFMPEG).
// Usage : FFMPEG=/chemin/ffmpeg node scripts/teaser.mjs [--apercu 12.3]
import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const FPS = 30;
const out = path.join(root, 'docs/teaser/terricom-teaser.mp4');
const frames = path.join(os.tmpdir(), 'terricom-teaser-images');
const ap = process.argv.indexOf('--apercu');

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
await page.goto(pathToFileURL(path.join(root, 'docs/teaser/clip.html')).href);
const duration = await page.evaluate(async () => {
  await window.clip.preload();
  return window.clip.duration;
});
const stage = page.locator('#stage');
if (ap > 0) {
  for (const t of process.argv.slice(ap + 1).map(Number)) {
    await page.evaluate((x) => window.clip.render(x), t);
    await stage.screenshot({ path: path.join(root, `docs/teaser/apercu-${t}.jpg`), type: 'jpeg', quality: 80 });
  }
  await browser.close();
  process.exit(0);
}
rmSync(frames, { recursive: true, force: true });
mkdirSync(frames, { recursive: true });
const total = Math.ceil(duration * FPS);
for (let f = 0; f < total; f++) {
  await page.evaluate((x) => window.clip.render(x), f / FPS);
  await stage.screenshot({ path: path.join(frames, `f${String(f).padStart(5, '0')}.jpg`), type: 'jpeg', quality: 90 });
  if (f % 150 === 0) console.log(`image ${f} / ${total}`);
}
await browser.close();
const ffmpeg = process.env.FFMPEG ?? 'ffmpeg';
execFileSync(
  ffmpeg,
  [
    '-y',
    '-loglevel',
    'error',
    '-framerate',
    String(FPS),
    '-i',
    path.join(frames, 'f%05d.jpg'),
    '-i',
    path.join(root, 'docs/teaser/musique.wav'),
    '-c:v',
    'libx264',
    '-preset',
    'slow',
    '-crf',
    '20',
    '-pix_fmt',
    'yuv420p',
    '-c:a',
    'aac',
    '-b:a',
    '192k',
    '-shortest',
    '-movflags',
    '+faststart',
    out,
  ],
  { stdio: 'inherit' },
);
rmSync(frames, { recursive: true, force: true });
console.log(`→ ${path.relative(root, out)}`);
