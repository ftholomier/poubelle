// Outils d'interface partagés par tous les écrans : état global, rendu, en-tête, barre de navigation, messages.

import { api } from "./api.js";

export const APP_VERSION = "18"; // à garder identique à APP_VERSION dans app/bootstrap.php
export const state = { user: null, demo: null, sections: null, email: false, agence: "" };
export const nav = { cleanup: null }; // appelé en quittant un écran (ex. arrêt d'un enregistrement)
/** Écrans ajoutés par les modules dans un dossier : ecransDossier.avis = ($c, visit, saver) => … ;
 *  les onglets s'enregistrent sous « onglet_<nom> » (photos, vente). */
export const ecransDossier = {};
/** Actions de la carte « Prochaine étape » : actionsDossier.envoyer_vendeur = (visit) => … */
export const actionsDossier = {};
/** Écrans principaux ajoutés par les modules : vues.acquereurs = (params) => … */
export const vues = {};
/** Fonctions de app.js utiles aux modules (renseignées au démarrage) : generate, openSendSheet, bindDocActions, makeSaver… */
export const outils = {};

export const $app = document.getElementById("app");

export const esc = (s) =>
  String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

export const fmtDuree = (s) => {
  s = Math.max(0, Math.round(s));
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
  return h ? `${h}:${String(m).padStart(2, "0")}:${String(sec).padStart(2, "0")}` : `${m}:${String(sec).padStart(2, "0")}`;
};
export const fmtDate = (iso) =>
  new Date(iso).toLocaleDateString("fr-FR", { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });
export const fmtJour = (iso) => new Date(iso).toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
export const fmtCourt = (iso) => new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "short" });
export const fmtHeure = (iso) => new Date(iso).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
export const fmtPrix = (v) => (v ? Number(String(v).replace(/[^\d.]/g, "")).toLocaleString("fr-FR") + " €" : "");
export const fmtEuros = (v, dec = 0) => Number(v || 0).toLocaleString("fr-FR", { maximumFractionDigits: dec, minimumFractionDigits: dec }) + " €";
export const champsOf = (v) => (Array.isArray(v.fiche?.champs) ? {} : v.fiche?.champs || {});
export const val = (v, k) => (champsOf(v)[k]?.valeur || "").trim();
export const ilYa = (iso) => {
  const s = (Date.now() - new Date(iso)) / 1000;
  if (s < 90) return "à l'instant";
  if (s < 3600) return `il y a ${Math.round(s / 60)} min`;
  if (s < 86400) return `il y a ${Math.round(s / 3600)} h`;
  return `il y a ${Math.round(s / 86400)} j`;
};

export function toast(message, type = "") {
  const el = document.createElement("div");
  el.className = `toast ${type}`;
  el.textContent = message;
  document.body.append(el);
  setTimeout(() => el.classList.add("out"), 2800);
  setTimeout(() => el.remove(), 3200);
}

export async function copier(texte) {
  try {
    await navigator.clipboard.writeText(texte);
    toast("Copié ✓");
  } catch {
    toast("Copie impossible sur cet appareil", "erreur");
  }
}

export function go(hash) {
  location.hash = hash;
}

export function render(html) {
  $app.innerHTML = html;
  window.scrollTo(0, 0);
}

export const theme = window.THEME || {};
export const logoSrc = () => `api/?r=logo&v=${theme.logoV || 1}`;
/** Logo : celui déposé dans les Paramètres, sinon le logo Synapse (version claire en mode sombre). */
export function logoImg(cls = "") {
  if (theme.logo) return `<img class="${cls}" src="${logoSrc()}" alt="${esc(theme.agence || "")}">`;
  return `<picture><source srcset="img/synapse-logo-clair.svg" media="(prefers-color-scheme: dark)"><img class="${cls}" src="img/synapse-logo.svg" alt="Synapse"></picture>`;
}

/* ---------- Compteur du coût de l'IA (toujours visible en haut à droite) ---------- */

const cout = { mois: 0, portee: "moi", direct: 0 };

/** 0,0042 € → « 0,004 € » : on garde des décimales tant que les montants sont petits. */
export const fmtCout = (v) => Number(v || 0).toLocaleString("fr-FR", { minimumFractionDigits: v < 1 ? 3 : 2, maximumFractionDigits: v < 1 ? 3 : 2 }) + " €";

export function afficherCout() {
  let el = document.getElementById("cout-ia");
  if (!state.user) return el?.remove();
  if (!el) {
    el = document.createElement("button");
    el.id = "cout-ia";
    el.type = "button";
    el.className = "cout-ia";
    el.onclick = () => {
      const qui = cout.portee === "agence" ? "de l'agence" : "de vos dossiers";
      toast(`Coût de l'IA ${qui} ce mois-ci : ≈ ${fmtCout(cout.mois + cout.direct)}${cout.direct ? ` (dont ${fmtCout(cout.direct)} pour la conversation en cours)` : ""}. Estimation d'après les jetons Gemini.`);
    };
    document.body.append(el);
    document.body.classList.add("avec-cout");
  }
  const total = cout.mois + cout.direct;
  const avant = el.dataset.v;
  el.innerHTML = `<span aria-hidden="true">🪙</span> ${fmtCout(total)}`;
  el.dataset.v = total.toFixed(5);
  el.setAttribute("aria-label", `Coût de l'IA ce mois-ci : environ ${fmtCout(total)}. Afficher le détail`);
  el.classList.toggle("en-direct", cout.direct > 0);
  if (avant !== undefined && avant !== el.dataset.v) {
    el.classList.remove("pulse");
    void el.offsetWidth; // relance l'animation
    el.classList.add("pulse");
  }
}

window.addEventListener("cout-ia", (e) => {
  const [euros, portee] = String(e.detail).split(";");
  cout.mois = Number(euros) || 0;
  cout.portee = portee || "moi";
  afficherCout();
});

/** Coût de la conversation vocale en cours, ajouté en direct au compteur (0 quand elle est enregistrée côté serveur). */
export function coutEnDirect(euros) {
  cout.direct = Math.max(0, euros || 0);
  afficherCout();
}

/** Conversation terminée : son coût passe dans le total du mois, en attendant le chiffre exact du serveur. */
export function coutEnregistre() {
  cout.mois += cout.direct;
  cout.direct = 0;
  afficherCout();
}

export function header(titre, { back = null, actions = "" } = {}) {
  return `<header class="bar">
    ${back ? `<a class="icon-btn" href="${back}" aria-label="Retour">←</a>` : `<a class="logo" href="#/">${logoImg()}</a>`}
    <h1>${esc(titre)}</h1>
    <div class="bar-actions">${actions}</div>
  </header>`;
}

/** Barre de navigation du bas : Aujourd'hui, Biens, Visite (bouton rouge), Acquéreurs, Agenda. */
export function tabbar(actif) {
  const item = (k, href, ic, label) => `<a href="${href}" class="${k === actif ? "on" : ""}"><span class="tb-ic">${ic}</span>${label}</a>`;
  return `<nav class="tabbar">
    ${item("aujourdhui", "#/", "◎", "Aujourd'hui")}
    ${item("biens", "#/biens", "▦", "Biens")}
    <a href="#/nouvelle" class="tb-rec" aria-label="Nouvelle visite"><span></span></a>
    ${item("acquereurs", "#/acquereurs", "◉", "Acquéreurs")}
    ${item("agenda", "#/agenda", "▤", "Agenda")}
  </nav>`;
}

/** Écran principal avec en-tête, contenu et barre du bas. */
export function ecran(titre, contenu, { actif = "", back = null, actions = "" } = {}) {
  render(`${header(titre, { back, actions: actions || (back ? "" : menuButton()) })}<main class="page avec-tabbar">${contenu}</main>${tabbar(actif)}`);
  if (!back) bindMenu();
  return document.querySelector("main");
}

export function demoBanner() {
  if (!state.demo) return "";
  return `<div class="banner">Mode démo : aucune clé Gemini configurée, la transcription et l'analyse sont simulées.
    ${state.user?.role === "admin" ? `<a href="#/reglages"><strong>⚙️ Configurer Gemini →</strong></a>` : "Demandez à un administrateur de la configurer."}</div>`;
}

export function menuButton() {
  return `<button class="icon-btn" id="menu-btn" aria-label="Menu">☰</button>`;
}

export function bindMenu() {
  const btn = document.getElementById("menu-btn");
  if (!btn) return;
  btn.onclick = () => {
    const admin = state.user.role === "admin";
    const sheet = document.createElement("div");
    sheet.className = "sheet-bg";
    sheet.innerHTML = `<div class="sheet">
      <div class="sheet-user">${esc(state.user.nom)}<span class="muted"> · ${esc(state.user.login)} · ${admin ? "Administrateur" : "Agent"}</span></div>
      <a href="#/tableau">📈 Mon tableau de bord</a>
      <a href="#/estimation">📊 Estimer un bien</a>
      <a href="#/prospection">🧭 Prospection</a>
      <a href="#/reseau">🤝 Réseau Synapse</a>
      <a href="#/reglages">⚙️ Paramètres</a>
      ${admin ? `<a href="#/equipe">👥 Gérer l'équipe</a>` : ""}
      <a href="#/compte">👤 Mon compte</a>
      <button id="logout">↪ Se déconnecter</button>
      <span class="sheet-version">Visite Immo · version ${APP_VERSION}</span>
    </div>`;
    sheet.onclick = (e) => (e.target === sheet || e.target.closest("a") ? sheet.remove() : null);
    document.body.append(sheet);
    sheet.querySelector("#logout").onclick = async () => {
      await api("logout", { method: "POST" }).catch(() => {});
      state.user = null;
      sheet.remove();
      go("/connexion");
    };
  };
}

/** Feuille glissante du bas (formulaire ou choix). Renvoie l'élément ; fermer() la retire. */
export function feuille(html, { onClose } = {}) {
  const sheet = document.createElement("div");
  sheet.className = "sheet-bg";
  sheet.innerHTML = `<div class="sheet sheet-form">${html}</div>`;
  const fermer = () => {
    sheet.remove();
    onClose?.();
  };
  sheet.addEventListener("click", (e) => (e.target === sheet || e.target.closest("[data-close]") ? fermer() : null));
  document.body.append(sheet);
  sheet.fermer = fermer;
  return sheet;
}

/** Bouton qui affiche « … » pendant une action asynchrone et signale l'erreur éventuelle. */
export function action(btn, fn) {
  if (!btn) return;
  btn.addEventListener("click", async (e) => {
    e.preventDefault();
    const texte = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "…";
    try {
      await fn(e);
    } catch (err) {
      toast(err.message, "erreur");
    } finally {
      if (btn.isConnected) {
        btn.disabled = false;
        btn.innerHTML = texte;
      }
    }
  });
}

export const pdfUrl = (id, doc, dl = false, extra = {}) => `api/?${new URLSearchParams({ r: "pdf", id, doc, ...extra, ...(dl ? { dl: 1 } : {}) })}`;

export function sourceChip(source) {
  return (
    {
      ia: '<span class="chip">IA</span>',
      dialogue: '<span class="chip chip-voix">DICTÉ</span>',
      document: '<span class="chip chip-doc">DOCUMENT</span>',
      public: '<span class="chip chip-pub">DONNÉE PUBLIQUE</span>',
    }[source] || ""
  );
}
