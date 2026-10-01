// Enregistrement au micro du téléphone, découpé en morceaux autonomes de quelques minutes.
// Chaque morceau est un fichier audio complet (on arrête et relance le MediaRecorder),
// ce qui permet de l'envoyer et de le transcrire pendant que la visite continue.

const SEGMENT_MS = 3 * 60 * 1000;
const MIME_CANDIDATES = ["audio/webm;codecs=opus", "audio/webm", "audio/mp4", "audio/ogg;codecs=opus"];

export function recordingSupported() {
  return Boolean(navigator.mediaDevices?.getUserMedia && window.MediaRecorder);
}

export class Recorder {
  /** @param {{onChunk:(blob:Blob, type:string, seconds:number)=>void, onTick?:(s:number)=>void, onInterrupted?:()=>void}} handlers */
  constructor(handlers) {
    this.h = handlers;
    this.state = "idle"; // idle | recording | paused | stopped
    this.elapsedBefore = 0; // secondes cumulées des morceaux terminés
    this.segmentStart = 0;
    this.pendingStops = [];
    this.onVisibility = () => this.checkAlive();
  }

  get elapsed() {
    const current = this.state === "recording" ? (Date.now() - this.segmentStart) / 1000 : 0;
    return Math.floor(this.elapsedBefore + current);
  }

  async start() {
    await this.openStream();
    this.mimeType = MIME_CANDIDATES.find((t) => MediaRecorder.isTypeSupported(t)) || "";
    this.startSegment();
    this.ticker = setInterval(() => this.h.onTick?.(this.elapsed), 500);
    document.addEventListener("visibilitychange", this.onVisibility);
    this.keepAwake();
  }

  async openStream() {
    this.stream = await navigator.mediaDevices.getUserMedia({
      audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
    });
    this.stream.getAudioTracks()[0].addEventListener("ended", () => this.handleInterruption());
  }

  startSegment() {
    const mr = new MediaRecorder(this.stream, { mimeType: this.mimeType || undefined, audioBitsPerSecond: 32000 });
    const parts = [];
    const startedAt = Date.now();
    let done;
    const stopped = new Promise((r) => (done = r));

    mr.ondataavailable = (e) => e.data.size && parts.push(e.data);
    mr.onstop = () => {
      const seconds = Math.round((Date.now() - startedAt) / 1000);
      const type = (mr.mimeType || this.mimeType || "audio/webm").split(";")[0];
      if (parts.length) this.h.onChunk(new Blob(parts, { type }), type, seconds);
      done();
    };
    mr.onerror = () => this.handleInterruption();

    mr.start(5000); // les données arrivent toutes les 5 s : peu de perte si le navigateur coupe
    this.mr = mr;
    this.segmentStart = startedAt;
    this.state = "recording";
    this.pendingStops.push(stopped);
    this.rotation = setTimeout(() => this.rotate(), SEGMENT_MS);
  }

  stopSegment() {
    clearTimeout(this.rotation);
    if (this.mr && this.mr.state !== "inactive") {
      this.elapsedBefore += (Date.now() - this.segmentStart) / 1000;
      this.mr.stop();
    }
  }

  rotate() {
    if (this.state !== "recording") return;
    this.stopSegment();
    this.startSegment();
  }

  pause() {
    if (this.state !== "recording") return;
    this.stopSegment();
    this.state = "paused";
    this.h.onTick?.(this.elapsed);
  }

  async resume() {
    if (this.state !== "paused") return;
    if (this.stream.getAudioTracks()[0]?.readyState !== "live") await this.openStream();
    this.startSegment();
  }

  /** Termine l'enregistrement ; résout quand le dernier morceau a été émis. */
  async stop() {
    this.stopSegment();
    this.state = "stopped";
    clearInterval(this.ticker);
    document.removeEventListener("visibilitychange", this.onVisibility);
    await Promise.all(this.pendingStops);
    this.stream?.getTracks().forEach((t) => t.stop());
    this.wakeLock?.release().catch(() => {});
  }

  // Le navigateur (souvent iOS) peut couper le micro : on sauve ce qui a été capté et on met en pause.
  handleInterruption() {
    if (this.state !== "recording") return;
    this.pause();
    this.h.onInterrupted?.();
  }

  checkAlive() {
    if (document.visibilityState !== "visible") return;
    if (this.state === "recording") {
      const live = this.stream.getAudioTracks()[0]?.readyState === "live" && this.mr?.state === "recording";
      if (!live) this.handleInterruption();
      else this.keepAwake();
    }
  }

  // Empêche la mise en veille de l'écran pendant l'enregistrement
  async keepAwake() {
    try {
      this.wakeLock = await navigator.wakeLock?.request("screen");
    } catch {
      /* non disponible : sans conséquence */
    }
  }
}
