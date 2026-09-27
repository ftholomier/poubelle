// Rendu du teaser : chaque image est calculée par docs/teaser/teaser.html (canevas 1920 × 1080), puis la vidéo
// est assemblée avec la musique (ffmpeg avec libx264, variable FFMPEG).
// Usage : node scripts/teaser/rendu.mjs --apercu 20 37.5 56   |   FFMPEG=… node scripts/teaser/rendu.mjs
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { RACINE, resoudre, type, W } from './commun.mjs';

const FPS = 30;
const server = createServer((req, res) => {
  const f = resoudre(decodeURIComponent(new URL(req.url, 'http://x').pathname.slice(1)));
  if (!f || !existsSync(f) || statSync(f).isDirectory()) return res.writeHead(404).end();
  res.writeHead(200, { 'content-type': type(f) }).end(readFileSync(f));
}).listen(8765);
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
page.on('pageerror', (e) => console.log('erreur', e.message));
await page.goto('http://localhost:8765/comp/teaser.html');
const duration = await page.evaluate(async () => {
  await window.clip.preload();
  return window.clip.duration;
});
const canvas = page.locator('#c');
const ap = process.argv.indexOf('--apercu');
if (ap > 0) {
  const dir = path.join(W, 'apercu');
  mkdirSync(dir, { recursive: true });
  for (const t of process.argv.slice(ap + 1).map(Number)) {
    await page.evaluate((x) => window.clip.render(x), t);
    await canvas.screenshot({ path: path.join(dir, `a-${t.toFixed(2).padStart(6, '0')}.png`) });
  }
  console.log(`aperçus → ${dir}`);
} else {
  const frames = path.join(W, 'frames');
  rmSync(frames, { recursive: true, force: true });
  mkdirSync(frames, { recursive: true });
  const total = Math.ceil(duration * FPS);
  for (let f = 0; f < total; f++) {
    await page.evaluate((x) => window.clip.render(x), f / FPS);
    await canvas.screenshot({ path: path.join(frames, `f${String(f).padStart(5, '0')}.jpg`), type: 'jpeg', quality: 92 });
    if (f % 300 === 0) console.log(`image ${f} / ${total}`);
  }
  const out = path.join(RACINE, 'docs/teaser/terricom-teaser.mp4');
  execFileSync(
    process.env.FFMPEG ?? 'ffmpeg',
    [
      '-y',
      '-loglevel',
      'error',
      '-framerate',
      String(FPS),
      '-i',
      path.join(frames, 'f%05d.jpg'),
      '-i',
      path.join(RACINE, 'docs/teaser/musique.mp3'),
      '-c:v',
      'libx264',
      '-preset',
      'slow',
      '-crf',
      '23',
      '-tune',
      'film',
      '-pix_fmt',
      'yuv420p',
      '-c:a',
      'aac',
      '-b:a',
      '192k',
      '-af',
      `apad,afade=t=out:st=${duration - 2.5}:d=2.5`,
      '-t',
      String(duration),
      '-movflags',
      '+faststart',
      out,
    ],
    { stdio: 'inherit' },
  );
  console.log(`→ ${path.relative(process.cwd(), out)}`);
}
await browser.close();
server.close();
