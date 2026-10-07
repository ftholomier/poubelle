// Pavé de signature au doigt (ou à la souris) sur un canvas : trait lissé, effacer, export PNG.
// Utilisé dans l'appli (signature sur place) et dans l'espace client (signature à distance).

export class PadSignature {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext("2d");
    this.vide = true;
    this.points = [];
    this.redimensionner();
    const pos = (e) => {
      const r = canvas.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top, t: Date.now() };
    };
    canvas.addEventListener("pointerdown", (e) => {
      e.preventDefault();
      canvas.setPointerCapture(e.pointerId);
      this.trace = true;
      this.points = [pos(e)];
      this.vide = false;
      this.onChange?.();
    });
    canvas.addEventListener("pointermove", (e) => {
      if (!this.trace) return;
      e.preventDefault();
      this.points.push(pos(e));
      this.dessiner();
    });
    const fin = () => (this.trace = false);
    canvas.addEventListener("pointerup", fin);
    canvas.addEventListener("pointercancel", fin);
    canvas.style.touchAction = "none";
  }

  redimensionner() {
    const ratio = Math.max(window.devicePixelRatio || 1, 2);
    const r = this.canvas.getBoundingClientRect();
    this.canvas.width = r.width * ratio;
    this.canvas.height = r.height * ratio;
    this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    this.ctx.lineCap = "round";
    this.ctx.lineJoin = "round";
    this.ctx.strokeStyle = "#111114";
    this.effacer();
  }

  dessiner() {
    const p = this.points;
    if (p.length < 3) return;
    const [a, b, c] = p.slice(-3);
    const vitesse = Math.hypot(c.x - b.x, c.y - b.y) / Math.max(1, c.t - b.t);
    this.ctx.lineWidth = Math.max(1.4, Math.min(3.2, 3.4 - vitesse * 1.2)); // trait plus fin quand on va vite
    this.ctx.beginPath();
    this.ctx.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
    this.ctx.quadraticCurveTo(b.x, b.y, (b.x + c.x) / 2, (b.y + c.y) / 2);
    this.ctx.stroke();
  }

  effacer() {
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.vide = true;
    this.onChange?.();
  }

  /** PNG recadré sur le tracé (fond transparent). */
  png() {
    const { width: w, height: h } = this.canvas;
    const data = this.ctx.getImageData(0, 0, w, h).data;
    let x0 = w, y0 = h, x1 = 0, y1 = 0;
    for (let y = 0; y < h; y += 2)
      for (let x = 0; x < w; x += 2)
        if (data[(y * w + x) * 4 + 3] > 10) {
          if (x < x0) x0 = x;
          if (x > x1) x1 = x;
          if (y < y0) y0 = y;
          if (y > y1) y1 = y;
        }
    if (x1 <= x0) return null;
    const m = 12;
    x0 = Math.max(0, x0 - m); y0 = Math.max(0, y0 - m); x1 = Math.min(w, x1 + m); y1 = Math.min(h, y1 + m);
    const c = document.createElement("canvas");
    c.width = x1 - x0;
    c.height = y1 - y0;
    c.getContext("2d").drawImage(this.canvas, x0, y0, c.width, c.height, 0, 0, c.width, c.height);
    return c.toDataURL("image/png");
  }
}
