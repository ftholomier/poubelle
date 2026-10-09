// Conversation vocale avec Gemini Live pour compléter le dossier.
//
// Économies :
//  - l'IA répond en TEXTE, lu par la voix du téléphone (gratuite) au lieu de la voix Gemini (la partie la plus chère) ;
//  - la détection de parole se fait sur le téléphone : seuls les moments où l'agent parle sont envoyés, pas les silences ;
//  - l'historique est compressé par Gemini (fenêtre glissante) pour que chaque échange ne refacture pas toute la conversation.
// Si le modèle choisi ne sait répondre qu'en voix (« native audio »), on bascule sur sa voix automatiquement.

const RATE_IN = 16000; // ce que Gemini attend en entrée
const RATE_OUT = 24000; // ce que Gemini renvoie quand il répond en voix
const FRAME = 320; // 20 ms à 16 kHz

// Module audio du micro : un vrai fichier du site (la politique de sécurité interdit les scripts créés à la volée)
const WORKLET = new URL("./worklets/capteur.js", import.meta.url).href;

function base64FromInt16(int16) {
  const bytes = new Uint8Array(int16.buffer, int16.byteOffset, int16.byteLength);
  let bin = "";
  for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
  return btoa(bin);
}

function int16FromBase64(b64) {
  const bin = atob(b64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return new Int16Array(bytes.buffer);
}

/** Choisit la voix française la plus naturelle disponible sur l'appareil. */
function voixFrancaise() {
  const voix = speechSynthesis.getVoices().filter((v) => v.lang?.toLowerCase().startsWith("fr"));
  const preferees = ["Google français", "Amélie", "Audrey", "Thomas", "Aurélie", "Marie", "Denise", "Henri", "Microsoft"];
  for (const p of preferees) {
    const v = voix.find((x) => x.name.includes(p));
    if (v) return v;
  }
  return voix.find((v) => v.lang === "fr-FR") || voix[0] || null;
}

export class Conversation {
  /**
   * @param {object} config  réponse de /api/?r=live : url, model, system, tools
   * @param {object} h       rappels : onState(etat), onIa(texte), onAgent(texte), onNoter(champs) → Promise<réponse>,
   *                         onTerminer(resume), onFin(raison), onUsage(usage)
   */
  constructor(config, h) {
    this.config = config;
    this.h = h;
    this.modalite = config.audio_natif ? "AUDIO" : "TEXT"; // les modèles « native audio » et Gemini 3 ne répondent qu'en voix
    this.texteDirect = !!config.texte_direct; // Gemini 3.1 : le texte passe par realtimeInput (clientContent refusé)
    this.texteIa = "";
    this.parle = false; // l'agent est en train de parler
    this.iaParle = false;
    this.pause = false;
    this.ferme = false;
    this.usage = { promptTokensDetails: {}, responseTokensDetails: {}, thoughtsTokenCount: 0 };
    // détection de parole
    this.bruit = 0.006;
    this.reste = new Float32Array(0);
    this.preroll = [];
    this.envoi = [];
    this.voixFrames = 0;
    this.silenceFrames = 0;
    this.lectureFin = 0;
  }

  async demarrer() {
    // Le contexte audio et la synthèse vocale doivent être débloqués pendant le geste de l'agent (iOS)
    this.ctx = new (window.AudioContext || window.webkitAudioContext)();
    await this.ctx.resume();
    speechSynthesis.cancel();
    speechSynthesis.speak(new SpeechSynthesisUtterance(""));
    this.voix = voixFrancaise();
    speechSynthesis.onvoiceschanged = () => (this.voix = voixFrancaise());

    this.stream = await navigator.mediaDevices.getUserMedia({
      audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
    });
    await this.ctx.audioWorklet.addModule(WORKLET);
    this.source = this.ctx.createMediaStreamSource(this.stream);
    this.node = new AudioWorkletNode(this.ctx, "capteur");
    this.node.port.onmessage = (e) => this.audioEntrant(e.data);
    this.source.connect(this.node);
    // le nœud doit être relié à la sortie pour tourner, sans rien faire entendre
    const muet = this.ctx.createGain();
    muet.gain.value = 0;
    this.node.connect(muet).connect(this.ctx.destination);

    this.wakeLock = await navigator.wakeLock?.request("screen").catch(() => null);
    this.connecter();
  }

  connecter() {
    this.h.onState("connexion");
    this.setupOk = false;
    const ws = new WebSocket(this.config.url);
    this.ws = ws;
    ws.onopen = () => {
      const generationConfig = { responseModalities: [this.modalite], temperature: 0.4 };
      const setup = {
        model: this.config.model,
        generationConfig,
        systemInstruction: { parts: [{ text: this.config.system }] },
        tools: this.config.tools,
        contextWindowCompression: { slidingWindow: {}, triggerTokens: "12000" },
        inputAudioTranscription: {},
      };
      // Modèles classiques : c'est le téléphone qui dit quand l'agent commence et finit de parler.
      // Gemini 3 (mode direct) : réglage standard de Google, qui détecte la parole lui-même.
      if (!this.texteDirect) setup.realtimeInputConfig = { automaticActivityDetection: { disabled: true } };
      if (this.modalite === "AUDIO") {
        setup.outputAudioTranscription = {};
        // les modèles en voix Gemini choisissent eux-mêmes la langue (imposer fr-FR peut être refusé) : les consignes en français suffisent
      }
      ws.send(JSON.stringify({ setup }));
    };
    ws.onmessage = async (e) => {
      const txt = typeof e.data === "string" ? e.data : await e.data.text();
      let msg;
      try {
        msg = JSON.parse(txt);
      } catch {
        return;
      }
      this.recevoir(msg);
    };
    ws.onclose = (e) => {
      if (this.ferme) return;
      // Le modèle ne sait pas répondre en texte : on repasse en voix Gemini
      if (!this.setupOk && this.modalite === "TEXT" && /modalit|TEXT|response|invalid argument/i.test(e.reason || "")) {
        this.modalite = "AUDIO";
        this.h.onInfo?.("Ce modèle répond uniquement en voix Gemini (plus coûteux).");
        this.h.onRelance?.(); // demande un nouveau jeton puis rappelle connecter()
        return;
      }
      // Texte refusé en clientContent (code 1007, cas de Gemini 3.1) : on rouvre la conversation en realtimeInput
      if (e.code === 1007 && this.dernierClientContent && !this.texteDirect) {
        this.texteDirect = true;
        this.h.onRelance?.();
        return;
      }
      const detail = `code ${e.code}${e.reason ? ` · ${e.reason}` : ""}${this.dernierEnvoi ? ` · après ${this.dernierEnvoi}` : ""}${this.texteDirect ? " · mode direct" : ""}`;
      this.h.onFin(this.setupOk ? `coupure : ${detail}` : `refus : ${e.reason || `connexion impossible (${detail})`}`);
    };
  }

  envoyer(obj) {
    // gardé pour le diagnostic d'une coupure (sans le contenu) : « realtimeInput.text », « toolResponse »…
    this.dernierEnvoi = Object.entries(obj).map(([k, v]) => (v && typeof v === "object" && !Array.isArray(v) ? `${k}.${Object.keys(v)[0]}` : k))[0];
    if (this.ws?.readyState === 1) this.ws.send(JSON.stringify(obj));
  }

  recevoir(msg) {
    if (msg.setupComplete) {
      this.setupOk = true;
      this.h.onState("ecoute");
      // L'IA ouvre la conversation avec la première question utile
      this.texte("Je suis prêt, posez-moi la première question.");
      this.h.onState("reflexion");
    }
    if (msg.usageMetadata) this.compter(msg.usageMetadata);
    if (msg.toolCall) this.outils(msg.toolCall.functionCalls || []);
    if (msg.goAway) this.h.onInfo?.("La session va bientôt se terminer.");

    const sc = msg.serverContent;
    if (!sc) return;
    if (sc.inputTranscription?.text) this.h.onAgent(sc.inputTranscription.text);
    if (sc.interrupted) this.taireIa();
    for (const part of sc.modelTurn?.parts || []) {
      if (part.thought) continue;
      if (part.text && this.modalite === "TEXT") this.texteIa += part.text;
      if (part.inlineData?.data && this.modalite === "AUDIO") this.jouer(part.inlineData.data);
    }
    if (sc.outputTranscription?.text) {
      this.texteIa += sc.outputTranscription.text;
      this.h.onIa(this.texteIa);
    }
    if (sc.turnComplete) {
      const texte = this.texteIa.trim();
      this.texteIa = "";
      if (texte && this.modalite === "TEXT") {
        this.h.onIa(texte);
        this.dire(texte);
      } else if (!this.iaParle) {
        this.h.onState("ecoute");
      }
      if (this.terminerApres) this.finir();
    }
  }

  // ---------- Voix de l'IA ----------

  dire(texte) {
    const u = new SpeechSynthesisUtterance(texte.replace(/[*_#`]/g, ""));
    u.lang = "fr-FR";
    if (this.voix) u.voice = this.voix;
    u.rate = 1.08;
    u.onstart = () => {
      this.iaParle = true;
      this.h.onState("ia");
    };
    u.onend = u.onerror = () => {
      this.iaParle = false;
      this.lectureFin = performance.now();
      if (!this.parle) this.h.onState(this.pause ? "pause" : "ecoute");
    };
    speechSynthesis.speak(u);
  }

  jouer(b64) {
    const pcm = int16FromBase64(b64);
    const buf = this.ctx.createBuffer(1, pcm.length, RATE_OUT);
    const ch = buf.getChannelData(0);
    for (let i = 0; i < pcm.length; i++) ch[i] = pcm[i] / 32768;
    const src = this.ctx.createBufferSource();
    src.buffer = buf;
    src.connect(this.ctx.destination);
    const debut = Math.max(this.ctx.currentTime, this.finLecture || 0);
    src.start(debut);
    this.finLecture = debut + buf.duration;
    this.sources = [...(this.sources || []), src];
    this.iaParle = true;
    this.h.onState("ia");
    src.onended = () => {
      if (this.ctx.currentTime >= (this.finLecture || 0) - 0.05) {
        this.iaParle = false;
        this.lectureFin = performance.now();
        if (!this.parle) this.h.onState("ecoute");
      }
    };
  }

  taireIa() {
    speechSynthesis.cancel();
    (this.sources || []).forEach((s) => {
      try {
        s.stop();
      } catch {
        /* déjà arrêtée */
      }
    });
    this.sources = [];
    this.finLecture = 0;
    this.iaParle = false;
  }

  // ---------- Micro et détection de parole ----------

  audioEntrant(f32) {
    if (this.ferme || !this.setupOk) return;
    // ré-échantillonnage vers 16 kHz (interpolation linéaire)
    const ratio = this.ctx.sampleRate / RATE_IN;
    const n = Math.floor((this.reste.length + f32.length) / ratio);
    const tout = new Float32Array(this.reste.length + f32.length);
    tout.set(this.reste);
    tout.set(f32, this.reste.length);
    const out = new Float32Array(n);
    for (let i = 0; i < n; i++) {
      const p = i * ratio;
      const a = Math.floor(p);
      out[i] = tout[a] + ((tout[a + 1] ?? tout[a]) - tout[a]) * (p - a);
    }
    this.reste = tout.slice(Math.floor(n * ratio));
    this.tampon = this.tampon ? concat(this.tampon, out) : out;
    while (this.tampon.length >= FRAME) {
      this.trame(this.tampon.slice(0, FRAME));
      this.tampon = this.tampon.slice(FRAME);
    }
  }

  trame(f) {
    if (this.pause) return;
    let somme = 0;
    for (const x of f) somme += x * x;
    const rms = Math.sqrt(somme / f.length);
    // Pendant que l'IA parle (et juste après), il faut parler plus fort pour lui couper la parole :
    // évite que le haut-parleur du téléphone soit pris pour l'agent.
    const iaRecente = this.iaParle || performance.now() - this.lectureFin < 400;
    const seuil = Math.max(0.012, this.bruit * 3) * (iaRecente ? 3.5 : 1);
    const pcm = new Int16Array(f.length);
    for (let i = 0; i < f.length; i++) pcm[i] = Math.max(-1, Math.min(1, f[i])) * 0x7fff;

    if (!this.parle) {
      if (rms < seuil) this.bruit = this.bruit * 0.98 + rms * 0.02; // bruit de fond
      this.preroll.push(pcm);
      if (this.preroll.length > 15) this.preroll.shift(); // 300 ms avant le début de la parole
      this.voixFrames = rms > seuil ? this.voixFrames + 1 : 0;
      if (this.voixFrames >= (iaRecente ? 8 : 3)) {
        this.parle = true;
        this.silenceFrames = 0;
        if (this.iaParle) this.taireIa(); // l'agent coupe la parole à l'IA
        this.debutParole();
        this.envoi = [...this.preroll];
        this.preroll = [];
        this.h.onState("agent");
      }
      return;
    }

    this.envoi.push(pcm);
    if (this.envoi.length >= 5) this.vider(); // paquets de 100 ms
    this.silenceFrames = rms < seuil * 0.8 ? this.silenceFrames + 1 : 0;
    if (this.silenceFrames >= 45) {
      // 900 ms de silence : fin de la réponse de l'agent
      this.vider();
      this.finParole();
      this.parle = false;
      this.voixFrames = 0;
      this.h.onState("reflexion");
    }
  }

  vider() {
    if (!this.envoi.length) return;
    const total = this.envoi.reduce((s, a) => s + a.length, 0);
    const pcm = new Int16Array(total);
    let o = 0;
    for (const a of this.envoi) {
      pcm.set(a, o);
      o += a.length;
    }
    this.envoi = [];
    this.envoyer({ realtimeInput: { audio: { data: base64FromInt16(pcm), mimeType: `audio/pcm;rate=${RATE_IN}` } } });
  }

  // ---------- Fonctions appelées par l'IA ----------

  async outils(appels) {
    const reponses = [];
    for (const a of appels) {
      let response = { resultat: "ok" };
      try {
        if (a.name === "noter") {
          response = await this.h.onNoter(a.args?.champs || []);
        } else if (a.name === "terminer") {
          this.terminerApres = true;
          this.h.onTerminer(a.args?.resume || "");
        }
      } catch (e) {
        response = { erreur: e.message };
      }
      reponses.push({ id: a.id, name: a.name, response });
    }
    this.envoyer({ toolResponse: { functionResponses: reponses } });
  }

  /** Prise de parole de l'agent : signalée à Gemini en mode classique ; en mode direct, Google la détecte seul. */
  debutParole() {
    if (!this.texteDirect) this.envoyer({ realtimeInput: { activityStart: {} } });
  }

  /** Fin de parole : en mode direct, « fin du flux audio » pour que Google réponde sans attendre. */
  finParole() {
    this.envoyer({ realtimeInput: this.texteDirect ? { audioStreamEnd: true } : { activityEnd: {} } });
  }

  /** Texte de l'agent. Gemini 3.1 n'accepte que realtimeInput.text (clientContent coupe la conversation). */
  texte(t) {
    if (this.texteDirect) {
      this.envoyer({ realtimeInput: { text: t } });
    } else {
      this.dernierClientContent = Date.now();
      this.envoyer({ clientContent: { turns: [{ role: "user", parts: [{ text: t }] }], turnComplete: true } });
    }
  }

  /** Commande d'un bouton (passer la question, la faire expliquer) : consigne pour l'assistant, libellé pour l'écran. */
  commande(consigne, libelle) {
    this.taireIa();
    this.texte(consigne);
    this.h.onAgent(libelle);
    this.h.onState("reflexion");
  }

  /** Réponse écrite (lieu bruyant, nom à épeler…). */
  ecrire(texte) {
    this.taireIa();
    this.texte(texte);
    this.h.onAgent(texte);
    this.h.onState("reflexion");
  }

  basculerPause() {
    this.pause = !this.pause;
    if (this.pause && this.parle) {
      this.vider();
      this.finParole();
      this.parle = false;
    }
    this.h.onState(this.pause ? "pause" : "ecoute");
    return this.pause;
  }

  // ---------- Coût ----------

  compter(u) {
    const ajouter = (cible, details) => {
      for (const d of details || []) cible[d.modality || "TEXT"] = (cible[d.modality || "TEXT"] || 0) + (d.tokenCount || 0);
    };
    ajouter(this.usage.promptTokensDetails, u.promptTokensDetails);
    ajouter(this.usage.responseTokensDetails, u.responseTokensDetails);
    if (!u.responseTokensDetails && u.responseTokenCount) this.usage.responseTokensDetails.TEXT = (this.usage.responseTokensDetails.TEXT || 0) + u.responseTokenCount;
    this.usage.thoughtsTokenCount += u.thoughtsTokenCount || 0;
  }

  /** Usage au format usageMetadata, pour le calcul du coût côté serveur. */
  usageTotal() {
    const liste = (o) => Object.entries(o).map(([modality, tokenCount]) => ({ modality, tokenCount }));
    return {
      promptTokensDetails: liste(this.usage.promptTokensDetails),
      responseTokensDetails: liste(this.usage.responseTokensDetails),
      thoughtsTokenCount: this.usage.thoughtsTokenCount,
    };
  }

  // ---------- Fin ----------

  finir() {
    if (this.ferme) return;
    const attendre = () => (this.iaParle ? setTimeout(attendre, 300) : this.fermer("termine"));
    attendre();
  }

  fermer(raison = "arret") {
    if (this.ferme) return;
    this.ferme = true;
    try {
      this.ws?.close();
    } catch {
      /* déjà fermée */
    }
    this.taireIa();
    this.stream?.getTracks().forEach((t) => t.stop());
    this.node?.disconnect();
    this.ctx?.close().catch(() => {});
    this.wakeLock?.release().catch(() => {});
    this.h.onUsage(this.usageTotal());
    this.h.onFin(raison);
  }
}

function concat(a, b) {
  const r = new Float32Array(a.length + b.length);
  r.set(a);
  r.set(b, a.length);
  return r;
}
