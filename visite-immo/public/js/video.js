// Courte vidéo du bien (format story 1080 × 1920), fabriquée dans le navigateur à partir des photos :
// effet de zoom lent sur chaque photo, titre, atouts, prix et logo. Prête pour Reels, TikTok, Shorts.

const W = 1080, H = 1920;
const COULEURS = { creme: "#f4f1ea", encre: "#111114", citron: "#d4f22e", orange: "#ff6b35" };

function charger(src) {
  return new Promise((ok, ko) => {
    const i = new Image();
    i.onload = () => ok(i);
    i.onerror = ko;
    i.src = src;
  });
}

function couvrir(ctx, img, zoom, dx) {
  const r = Math.max(W / img.width, H / img.height) * zoom;
  const w = img.width * r, h = img.height * r;
  ctx.drawImage(img, (W - w) / 2 + dx, (H - h) / 2, w, h);
}

function texte(ctx, t, x, y, taille, couleur, police = "800", largeurMax = W - 140) {
  ctx.font = `${police} ${taille}px Archivo, sans-serif`;
  ctx.fillStyle = couleur;
  const mots = t.split(/\s+/);
  const lignes = [];
  let cour = "";
  for (const m of mots) {
    const essai = cour ? `${cour} ${m}` : m;
    if (cour && ctx.measureText(essai).width > largeurMax) { lignes.push(cour); cour = m; } else cour = essai;
  }
  if (cour) lignes.push(cour);
  lignes.forEach((l, i) => ctx.fillText(l, x, y + i * taille * 1.15));
  return lignes.length;
}

/**
 * Fabrique la vidéo ; onProgres(0..1). Renvoie un Blob (webm ou mp4 selon le navigateur).
 * @param {{photos:string[], titre:string, prix:string, atouts:string[], infos:string, agence:string, logo:string}} d
 */
export async function fabriquerVideo(d, onProgres = () => {}) {
  const canvas = document.createElement("canvas");
  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext("2d");
  const photos = (await Promise.all(d.photos.slice(0, 6).map((s) => charger(s).catch(() => null)))).filter(Boolean);
  const logo = await charger(d.logo).catch(() => null);
  const type = ["video/mp4;codecs=avc1", "video/mp4", "video/webm;codecs=vp9", "video/webm"].find((t) => MediaRecorder.isTypeSupported(t)) || "";
  const rec = new MediaRecorder(canvas.captureStream(30), { mimeType: type, videoBitsPerSecond: 5_000_000 });
  const morceaux = [];
  rec.ondataavailable = (e) => e.data.size && morceaux.push(e.data);
  const fini = new Promise((ok) => (rec.onstop = ok));

  const parPhoto = 2.6, intro = 2.4, fin = 3;
  const duree = intro + Math.max(1, photos.length) * parPhoto + fin;
  rec.start(500);
  const t0 = performance.now();
  await new Promise((ok) => {
    const image = () => {
      const t = (performance.now() - t0) / 1000;
      onProgres(Math.min(1, t / duree));
      ctx.fillStyle = COULEURS.creme;
      ctx.fillRect(0, 0, W, H);
      if (t < intro) {
        // Carton d'ouverture
        if (photos[0]) { couvrir(ctx, photos[0], 1.05 + t * 0.02, 0); ctx.fillStyle = "rgba(17,17,20,.45)"; ctx.fillRect(0, 0, W, H); }
        ctx.save();
        ctx.translate(80, 700);
        ctx.rotate(-0.035);
        ctx.fillStyle = COULEURS.orange;
        ctx.fillRect(0, 0, 360, 84);
        ctx.font = "700 38px 'JetBrains Mono', monospace";
        ctx.fillStyle = COULEURS.encre;
        ctx.fillText("À VENDRE", 36, 56);
        ctx.restore();
        texte(ctx, d.titre, 80, 900, 96, COULEURS.creme);
      } else if (t < intro + photos.length * parPhoto) {
        const i = Math.floor((t - intro) / parPhoto), local = (t - intro - i * parPhoto) / parPhoto;
        couvrir(ctx, photos[i], 1.04 + local * 0.08, (i % 2 ? -1 : 1) * local * 30);
        const atout = d.atouts[i];
        if (atout) {
          ctx.fillStyle = COULEURS.encre;
          ctx.fillRect(60, H - 420, W - 120, 150);
          texte(ctx, atout, 100, H - 330, 56, COULEURS.citron, "800", W - 200);
        }
      } else {
        // Carton de fin : prix et contact
        ctx.fillStyle = COULEURS.citron;
        ctx.fillRect(0, 0, W, H);
        texte(ctx, d.titre, 80, 520, 80, COULEURS.encre);
        ctx.font = "700 40px 'JetBrains Mono', monospace";
        ctx.fillStyle = COULEURS.encre;
        ctx.fillText(d.infos.toUpperCase(), 80, 980);
        ctx.fillStyle = COULEURS.encre;
        ctx.fillRect(0, H - 520, W, 520);
        texte(ctx, d.prix, 80, H - 380, 120, COULEURS.creme);
        ctx.font = "700 40px 'JetBrains Mono', monospace";
        ctx.fillStyle = COULEURS.citron;
        ctx.fillText(d.agence.toUpperCase(), 80, H - 140);
        if (logo) ctx.drawImage(logo, 80, 120, 360, (360 * logo.height) / logo.width);
      }
      if (t >= duree) return ok();
      requestAnimationFrame(image);
    };
    image();
  });
  rec.stop();
  await fini;
  return new Blob(morceaux, { type: (rec.mimeType || type || "video/webm").split(";")[0] });
}
