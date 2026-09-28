// Découpe une capture pleine page en tranches lisibles : node scripts/crop.mjs <in.png> [hauteur]
import sharp from 'sharp';
const [, , input, hh = '1300'] = process.argv;
const meta = await sharp(input).metadata();
const step = Number(hh);
let i = 0;
for (let y = 0; y < meta.height; y += step, i++) {
  const out = input.replace(/\.png$/, `_${i}.png`);
  await sharp(input).extract({ left: 0, top: y, width: meta.width, height: Math.min(step, meta.height - y) }).toFile(out);
  console.log(out);
}
