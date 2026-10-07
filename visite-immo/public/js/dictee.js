// Dictée courte : un gros bouton, on parle, l'IA range l'information (fiche acquéreur, retour de visite,
// bilan d'appel, rendez-vous, offre…). On peut aussi écrire si on ne peut pas parler.

import { api } from "./api.js";
import { esc, feuille, toast, state } from "./ui.js";

const TYPES_AUDIO = ["audio/webm;codecs=opus", "audio/webm", "audio/mp4", "audio/ogg;codecs=opus"];
const MAX_SECONDES = 150;

/**
 * Ouvre la feuille de dictée. Résout avec la réponse du serveur ({transcription, donnees, resultat}) ou null si annulé.
 * @param {string} type  type de dictée déclaré côté serveur
 * @param {{titre:string, aide?:string, exemple?:string, params?:object}} opts
 */
export function dicter(type, { titre, aide = "", exemple = "", params = {} } = {}) {
  return new Promise((resolve) => {
    let rec = null, stream = null, morceaux = [], debut = 0, minuteur = null, fini = false;
    const sheet = feuille(
      `<div class="dictee">
        <h2>${esc(titre)}</h2>
        ${aide ? `<p class="muted small">${esc(aide)}</p>` : ""}
        ${exemple ? `<p class="dictee-exemple">« ${esc(exemple)} »</p>` : ""}
        <div class="dictee-zone">
          <button class="rec-btn" id="d-rec" aria-label="Parler"><span></span></button>
          <div class="timer petit" id="d-temps">Appuyez et parlez</div>
        </div>
        <details class="aide"><summary>Écrire plutôt</summary>
          <textarea id="d-texte" rows="4" placeholder="Tapez votre texte…"></textarea>
          <button class="btn primary" id="d-envoyer-texte">Envoyer le texte</button>
        </details>
        <p class="small center" id="d-etat"></p>
        <button class="btn ghost" data-close>Annuler</button>
      </div>`,
      { onClose: () => finir(null) },
    );
    const $ = (id) => sheet.querySelector(`#${id}`);

    const finir = (r) => {
      if (fini) return;
      fini = true;
      clearInterval(minuteur);
      stream?.getTracks().forEach((t) => t.stop());
      if (sheet.isConnected) sheet.remove();
      resolve(r);
    };

    const envoyer = async (blob, texte = "") => {
      $("d-etat").textContent = "L'IA range l'information…";
      $("d-rec").disabled = true;
      const fd = new FormData();
      if (blob) {
        fd.append("audio", blob, "dictee");
        fd.append("type_audio", blob.type);
      }
      fd.append("texte", texte);
      for (const [k, v] of Object.entries(params)) fd.append(k, v ?? "");
      try {
        const r = await api("dictee", { method: "POST", query: { type }, form: fd });
        navigator.vibrate?.([50, 40, 50]);
        finir(r);
      } catch (e) {
        $("d-etat").textContent = "";
        $("d-rec").disabled = false;
        toast(e.message, "erreur");
      }
    };

    $("d-rec").onclick = async () => {
      if (rec?.state === "recording") return rec.stop();
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, channelCount: 1 } });
      } catch {
        return toast("Micro indisponible : écrivez plutôt.", "erreur");
      }
      const mimeType = TYPES_AUDIO.find((t) => MediaRecorder.isTypeSupported(t)) || "";
      rec = new MediaRecorder(stream, mimeType ? { mimeType, audioBitsPerSecond: 32000 } : {});
      morceaux = [];
      rec.ondataavailable = (e) => e.data.size && morceaux.push(e.data);
      rec.onstop = () => {
        clearInterval(minuteur);
        stream.getTracks().forEach((t) => t.stop());
        $("d-rec").className = "rec-btn";
        envoyer(new Blob(morceaux, { type: (rec.mimeType || mimeType || "audio/webm").split(";")[0] }));
      };
      rec.start(1000);
      debut = Date.now();
      $("d-rec").className = "rec-btn recording";
      navigator.vibrate?.(40);
      minuteur = setInterval(() => {
        const s = Math.round((Date.now() - debut) / 1000);
        $("d-temps").textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")} · appuyez pour terminer`;
        if (s >= MAX_SECONDES) rec.stop();
      }, 300);
    };
    $("d-envoyer-texte").onclick = () => {
      const t = $("d-texte").value.trim();
      if (!t) return toast("Écrivez quelque chose", "erreur");
      envoyer(null, t);
    };
    if (state.demo) $("d-etat").textContent = "Mode démo : le résultat est simulé.";
  });
}
