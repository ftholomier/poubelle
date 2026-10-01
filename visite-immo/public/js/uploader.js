// File d'envoi des morceaux audio.
// Chaque morceau est d'abord rangé dans le téléphone (IndexedDB), puis envoyé au serveur.
// Sans réseau, il attend et repart automatiquement : rien n'est perdu, même si l'appli est fermée.

import { api, ApiError } from "./api.js";

const DB_NAME = "visite-immo";
const STORE = "morceaux";

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: "key" });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function tx(mode, fn) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const t = db.transaction(STORE, mode);
    const result = fn(t.objectStore(STORE));
    t.oncomplete = () => resolve(result?.result ?? result);
    t.onerror = () => reject(t.error);
  });
}

const listeners = new Set();
let running = null;
let lastError = null;

function notify() {
  listeners.forEach((fn) => fn());
}

export const uploader = {
  onChange(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
  },

  get lastError() {
    return lastError;
  },

  async add(visitId, n, blob, type, duree) {
    await tx("readwrite", (s) => s.put({ key: `${visitId}:${String(n).padStart(3, "0")}`, visitId, n, blob, type, duree, ajoute: Date.now() }));
    notify();
    this.run();
  },

  async pending(visitId) {
    const all = (await tx("readonly", (s) => s.getAll())) || [];
    return visitId ? all.filter((c) => c.visitId === visitId) : all;
  },

  /** Envoie tout ce qui est en attente, un morceau à la fois. */
  run() {
    if (!running) running = this.loop().finally(() => (running = null));
    return running;
  },

  async loop() {
    let delay = 2000;
    for (;;) {
      const [next] = (await this.pending()).sort((a, b) => a.key.localeCompare(b.key));
      if (!next) {
        lastError = null;
        notify();
        return;
      }
      try {
        const form = new FormData();
        form.append("type", next.type);
        form.append("audio", next.blob, `morceau-${next.n}`);
        await api("chunk", { method: "POST", query: { id: next.visitId, n: next.n, duree: next.duree }, form });
        await tx("readwrite", (s) => s.delete(next.key));
        lastError = null;
        delay = 2000;
        notify();
      } catch (e) {
        lastError = e.message;
        notify();
        const definitive = e instanceof ApiError && [400, 404, 409, 413, 415].includes(e.status);
        if (definitive) {
          await tx("readwrite", (s) => s.delete(next.key)); // inutile d'insister
          continue;
        }
        if (e.status === 401) return; // reconnexion nécessaire ; on reprendra après
        await new Promise((r) => setTimeout(r, delay));
        delay = Math.min(delay * 2, 30000);
      }
    }
  },

  /** Attend que tous les morceaux d'une visite soient arrivés au serveur. */
  async drain(visitId) {
    await this.run();
    if ((await this.pending(visitId)).length) throw new Error(lastError || "Envoi de l'audio en attente.");
  },
};

window.addEventListener("online", () => uploader.run());
