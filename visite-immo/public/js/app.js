// Appli Visite Immo : navigation, écrans et logique d'interface.

import { api, audioUrl } from "./api.js";
import { Recorder, recordingSupported } from "./recorder.js";
import { uploader } from "./uploader.js";
import { Conversation } from "./dialogue.js";

const $app = document.getElementById("app");
const APP_VERSION = "9"; // affichée dans le menu pour vérifier qu'on a la dernière version
const state = { user: null, demo: null, sections: null };

// ---------- Utilitaires ----------

const esc = (s) =>
  String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

const fmtDuree = (s) => {
  s = Math.max(0, Math.round(s));
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
  return h ? `${h}:${String(m).padStart(2, "0")}:${String(sec).padStart(2, "0")}` : `${m}:${String(sec).padStart(2, "0")}`;
};
const fmtDate = (iso) =>
  new Date(iso).toLocaleDateString("fr-FR", { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });
const fmtPrix = (v) => (v ? Number(v).toLocaleString("fr-FR") + " €" : "");
const champsOf = (v) => (Array.isArray(v.fiche?.champs) ? {} : v.fiche?.champs || {});

const STATUTS = {
  enregistrement: ["En cours", "gris"],
  enregistre: ["À générer", "orange"],
  generation: ["Génération…", "bleu"],
  pret: ["Fiche prête", "vert"],
  erreur: ["Erreur", "rouge"],
};

function toast(message, type = "") {
  const el = document.createElement("div");
  el.className = `toast ${type}`;
  el.textContent = message;
  document.body.append(el);
  setTimeout(() => el.classList.add("out"), 2600);
  setTimeout(() => el.remove(), 3000);
}

async function copier(texte) {
  try {
    await navigator.clipboard.writeText(texte);
    toast("Copié ✓");
  } catch {
    toast("Copie impossible sur cet appareil", "erreur");
  }
}

function go(hash) {
  location.hash = hash;
}

let cleanup = null; // appelé en quittant un écran (ex. arrêt d'un enregistrement)

function render(html) {
  $app.innerHTML = html;
  window.scrollTo(0, 0);
}

const theme = window.THEME || {};
const logoSrc = () => `api/?r=logo&v=${theme.logoV || 1}`;
/** Logo : celui déposé dans les Paramètres, sinon le logo Synapse (version claire en mode sombre). */
function logoImg(cls = "") {
  if (theme.logo) return `<img class="${cls}" src="${logoSrc()}" alt="${esc(theme.agence || "")}">`;
  return `<picture><source srcset="img/synapse-logo-clair.svg" media="(prefers-color-scheme: dark)"><img class="${cls}" src="img/synapse-logo.svg" alt="Synapse"></picture>`;
}
function logoMark() {
  return `<a class="logo" href="#/">${logoImg()}</a>`;
}

function header(titre, { back = null, actions = "" } = {}) {
  return `<header class="bar">
    ${back ? `<a class="icon-btn" href="${back}" aria-label="Retour">←</a>` : logoMark()}
    <h1>${esc(titre)}</h1>
    <div class="bar-actions">${actions}</div>
  </header>`;
}

function demoBanner() {
  if (!state.demo) return "";
  return `<div class="banner">Mode démo : aucune clé Gemini configurée, la transcription et l'analyse sont simulées.
    ${state.user?.role === "admin" ? `<a href="#/reglages"><strong>⚙️ Configurer Gemini →</strong></a>` : "Demandez à un administrateur de la configurer."}</div>`;
}

// ---------- Routeur ----------

async function route() {
  if (cleanup) {
    const ok = await cleanup();
    if (ok === false) return; // l'écran a refusé de se fermer
    cleanup = null;
  }
  const hash = location.hash.slice(1) || "/";
  if (!state.user && hash !== "/connexion") return go("/connexion");

  const [, page, id] = hash.split("/");
  try {
    if (page === "connexion") return viewLogin();
    if (page === "nouvelle") return viewRecord(null);
    if (page === "continuer") return viewRecord(id);
    if (page === "visite" && hash.split("/")[3] === "dialogue") return viewDialogue(id);
    if (page === "visite") return viewVisit(id, hash.split("/")[3] || "fiche");
    if (page === "equipe") return viewUsers();
    if (page === "reglages") return viewSettings();
    if (page === "compte") return viewAccount();
    return viewHome();
  } catch (e) {
    render(`${header("Erreur", { back: "#/" })}<main class="page"><p class="erreur">${esc(e.message)}</p></main>`);
  }
}

window.addEventListener("hashchange", route);
window.addEventListener("session-expired", () => {
  state.user = null;
  go("/connexion");
});

// ---------- Connexion ----------

function viewLogin() {
  const setup = state.setup;
  render(`<main class="page login">
    ${logoImg("login-img")}
    <div><span class="tag">Visite immo · l'outil des agents</span></div>
    <h1>Vous parlez.<br><mark>L'IA rédige.</mark><br>Vous signez.</h1>
    <p class="muted">${setup ? "Première utilisation : créez le compte administrateur." : "Enregistrez la visite, la fiche, l'annonce et les rapports sont prêts en une minute."}</p>
    <form id="f" class="card">
      ${setup ? `<label>Votre nom<input name="nom" required autocomplete="name" placeholder="Prénom Nom"></label>` : ""}
      <label>Identifiant<input name="login" required autocapitalize="none" autocomplete="username"></label>
      <label>Mot de passe<input name="password" type="password" required autocomplete="${setup ? "new-password" : "current-password"}" minlength="${setup ? 8 : 1}"></label>
      <button class="btn primary big">${setup ? "Créer le compte" : "Se connecter"}</button>
      <p class="erreur" id="err"></p>
    </form>
    <div class="ticker">Enregistrez <b>★</b> Créez la fiche <b>★</b> Envoyez le PDF <b>★</b> On ne raconte pas de salades</div>
  </main>`);
  document.getElementById("f").onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector("button");
    btn.disabled = true;
    try {
      const { user } = await api(setup ? "setup" : "login", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
      state.user = user;
      state.setup = false;
      uploader.run();
      go("/");
    } catch (err) {
      document.getElementById("err").textContent = err.message;
      btn.disabled = false;
    }
  };
}

// ---------- Accueil : mes visites ----------

async function viewHome() {
  render(`${header("", { actions: menuButton() })}<main class="page"><div class="loader"></div></main>`);
  bindMenu();
  const [visites, pending] = await Promise.all([api("visits"), uploader.pending()]);
  const enAttente = new Set(pending.map((p) => p.visitId));

  const items = visites.length
    ? visites
        .map((v) => {
          const [label, couleur] = STATUTS[v.statut] || [v.statut, "gris"];
          const details = [v.type_bien, v.ville, fmtPrix(v.prix)].filter(Boolean).join(" · ");
          return `<a class="visite card" href="#/visite/${v.id}">
            <div class="visite-top"><strong>${esc(v.titre)}</strong><span class="badge ${couleur}">${label}</span></div>
            ${details ? `<div class="muted">${esc(details)}</div>` : ""}
            <div class="visite-meta">${fmtDate(v.cree_le)} · 🎙️ ${fmtDuree(v.duree)}${v.audio ? "" : " (audio supprimé)"}${enAttente.has(v.id) ? ' · <span class="orange-txt">⏳ envoi en attente</span>' : ""}</div>
          </a>`;
        })
        .join("")
    : `<div class="vide"><p>Aucune visite pour l'instant.</p><p class="muted">Appuyez sur le bouton rouge pour enregistrer votre première visite.</p></div>`;

  const prenom = (state.user.nom || "").split(" ")[0];
  const hero = `<div class="home-hero"><span class="tag">${visites.length} visite${visites.length > 1 ? "s" : ""}</span><h2 style="margin-top:14px">Bonjour ${esc(prenom)}.<br><mark>Vos visites.</mark></h2></div>`;
  document.querySelector("main").innerHTML = `${hero}${demoBanner()}${items}<div class="spacer"></div>`;
  document.querySelector("main").insertAdjacentHTML("afterend", `<a class="fab" href="#/nouvelle"><span class="dot"></span> Nouvelle visite</a>`);
}

function menuButton() {
  return `<a class="icon-btn" href="#/reglages" aria-label="Paramètres">⚙️</a><button class="icon-btn" id="menu-btn" aria-label="Menu">☰</button>`;
}

function bindMenu() {
  document.getElementById("menu-btn").onclick = () => {
    const sheet = document.createElement("div");
    sheet.className = "sheet-bg";
    sheet.innerHTML = `<div class="sheet">
      <div class="sheet-user">${esc(state.user.nom)}<span class="muted"> · ${esc(state.user.login)} · ${state.user.role === "admin" ? "Administrateur" : "Agent"}</span></div>
      <a href="#/reglages">⚙️ Paramètres (IA Gemini, stockage, signature)</a>
      ${state.user.role === "admin" ? `<a href="#/equipe">👥 Gérer l'équipe</a>` : ""}
      <a href="#/compte">👤 Mon compte (coordonnées, mot de passe)</a>
      <button id="logout">↪ Se déconnecter</button>
      <span class="sheet-version">Visite Immo · version ${APP_VERSION}</span>
    </div>`;
    sheet.onclick = (e) => e.target === sheet || e.target.closest("a") ? sheet.remove() : null;
    document.body.append(sheet);
    sheet.querySelector("#logout").onclick = async () => {
      await api("logout", { method: "POST" }).catch(() => {});
      state.user = null;
      sheet.remove();
      go("/connexion");
    };
  };
}

// ---------- Enregistrement ----------

async function viewRecord(existingId) {
  if (!recordingSupported()) {
    return render(`${header("Nouvelle visite", { back: "#/" })}<main class="page"><p class="erreur">Ce navigateur ne permet pas d'enregistrer. Utilisez Safari (iPhone) ou Chrome (Android), en HTTPS.</p></main>`);
  }

  let visit = existingId ? await api("visit", { query: { id: existingId } }) : null;
  let nextN = 0;
  if (visit) {
    const local = await uploader.pending(visit.id);
    nextN = Math.max(-1, ...visit.morceaux.map((m) => m.n), ...local.map((m) => m.n)) + 1;
  }
  let rec = null;

  render(`${header(visit ? "Reprendre l'enregistrement" : "Nouvelle visite", { back: visit ? `#/visite/${visit.id}` : "#/" })}
  <main class="page record">
    ${visit ? `<p class="muted center">${esc(visit.titre || "Visite")} : l'audio sera ajouté à la suite.</p>` : `
    <div class="card prep" id="prep">
      <label>Adresse ou nom du bien <span class="muted">(facultatif)</span><input id="titre" placeholder="ex. 12 rue des Lilas, Nantes"></label>
      <label class="check"><input type="checkbox" id="consent"> Le vendeur est informé et d'accord pour que l'échange soit enregistré.</label>
    </div>`}
    <div class="rec-zone">
      <div class="timer" id="timer">0:00</div>
      <div class="rec-status" id="status">Appuyez pour démarrer</div>
      <button class="rec-btn" id="rec" aria-label="Enregistrer"><span></span></button>
      <div class="rec-actions" id="actions" hidden>
        <button class="btn" id="pause">⏸ Pause</button>
        <button class="btn primary" id="stop">■ Terminer</button>
      </div>
    </div>
    <div class="upload-info muted center" id="upload"></div>
  </main>`);

  const $ = (id) => document.getElementById(id);
  const updateUpload = async () => {
    if (!visit || !$("upload")) return;
    const n = (await uploader.pending(visit.id)).length;
    if (!$("upload")) return;
    $("upload").textContent = n ? `⏳ ${n} morceau(x) à envoyer${uploader.lastError ? ` (${uploader.lastError})` : ""}` : "☁️ Audio sauvegardé au fur et à mesure";
  };
  const unsubscribe = uploader.onChange(updateUpload);

  const setUi = (mode) => {
    $("rec").className = `rec-btn ${mode}`;
    $("actions").hidden = mode === "idle";
    $("pause").textContent = mode === "paused" ? "● Reprendre" : "⏸ Pause";
    $("status").textContent = { idle: "Appuyez pour démarrer", recording: "Enregistrement en cours", paused: "En pause" }[mode];
  };

  const startRecording = async () => {
    if (!visit) {
      if (!$("consent").checked) {
        $("consent").closest("label").classList.add("shake");
        setTimeout(() => $("consent").closest("label").classList.remove("shake"), 500);
        return toast("Cochez l'accord du vendeur avant d'enregistrer", "erreur");
      }
    }
    rec = new Recorder({
      onTick: (s) => ($("timer").textContent = fmtDuree(s)),
      onChunk: (blob, type, secondes) => uploader.add(visit.id, nextN++, blob, type, secondes),
      onInterrupted: () => {
        setUi("paused");
        toast("Enregistrement interrompu par le téléphone : appuyez sur Reprendre", "erreur");
        navigator.vibrate?.([200, 100, 200]);
      },
    });
    try {
      await rec.start(); // demande l'accès au micro avant de créer la visite
    } catch (e) {
      rec = null;
      return toast(e.name === "NotAllowedError" ? "Accès au micro refusé : autorisez-le dans les réglages du navigateur" : `Micro indisponible : ${e.message}`, "erreur");
    }
    if (!visit) {
      try {
        visit = await api("visits", { method: "POST", body: { titre: $("titre").value, consentement: true } });
        history.replaceState(null, "", `#/continuer/${visit.id}`); // un rechargement reprend la même visite
      } catch (e) {
        await rec.stop();
        rec = null;
        return toast(e.message, "erreur");
      }
      $("prep")?.remove();
    }
    navigator.vibrate?.(60);
    setUi("recording");
    updateUpload();
  };

  $("rec").onclick = async () => {
    if (!rec) return startRecording();
    if (rec.state === "recording") {
      rec.pause();
      setUi("paused");
    } else if (rec.state === "paused") {
      await rec.resume();
      setUi("recording");
    }
  };
  $("pause").onclick = () => $("rec").onclick();
  $("stop").onclick = async () => {
    await rec.stop();
    rec = null;
    navigator.vibrate?.(60);
    finish();
  };

  // Écran de fin : bouton « Créer la fiche »
  const finish = () => {
    document.querySelector(".rec-zone").innerHTML = `
      <div class="done-icon">✓</div>
      <div class="rec-status">Visite enregistrée · ${$("timer").textContent}</div>
      <button class="btn magic big" id="gen">✨ Créer la fiche</button>
      <button class="btn ghost" id="more">● Reprendre l'enregistrement</button>`;
    $("gen").onclick = () => generate(visit.id);
    $("more").onclick = () => {
      unsubscribe();
      viewRecord(visit.id);
    };
  };

  cleanup = async () => {
    unsubscribe();
    if (rec && rec.state !== "stopped") {
      if (!confirm("Arrêter l'enregistrement en cours ?")) {
        history.pushState(null, "", visit ? `#/continuer/${visit.id}` : "#/nouvelle");
        return false;
      }
      await rec.stop();
    }
  };
  window.onbeforeunload = () => (rec && rec.state !== "stopped" ? "Enregistrement en cours" : undefined);
}

// ---------- Génération ----------

async function generate(id) {
  const etapes = ["Envoi des derniers morceaux…", "Transcription de la visite…", "Remplissage de la fiche…", "Rédaction de l'annonce…", "Rédaction des rapports…"];
  render(`${header("Création de la fiche")}<main class="page generating">
    <div class="spinner"></div>
    <div class="gen-step" id="step">${etapes[0]}</div>
    <p class="muted center">Vous pouvez ranger le téléphone, cela prend moins d'une minute.</p>
  </main>`);
  const $step = document.getElementById("step");
  try {
    await uploader.drain(id);
    let i = 1;
    $step.textContent = etapes[i];
    const timer = setInterval(() => ($step.textContent = etapes[Math.min(++i, etapes.length - 1)]), 6000);
    try {
      await api("generate", { method: "POST", query: { id } });
    } finally {
      clearInterval(timer);
    }
    navigator.vibrate?.([80, 60, 80]);
    toast("Fiche créée ✨", "ok");
    go(`/visite/${id}/fiche`);
  } catch (e) {
    render(`${header("Création de la fiche", { back: `#/visite/${id}` })}<main class="page generating">
      <p class="erreur center">${esc(e.message)}</p>
      <button class="btn magic big" id="retry">Réessayer</button>
    </main>`);
    document.getElementById("retry").onclick = () => generate(id);
  }
}

// ---------- Visite : fiche, annonce, rapports, audio ----------

const ONGLETS = [
  ["fiche", "Fiche"],
  ["annonce", "Annonce"],
  ["rapport", "Rapport"],
  ["vendeur", "Vendeur"],
  ["audio", "Audio"],
];

async function viewVisit(id, onglet) {
  const [visit, sections] = await Promise.all([api("visit", { query: { id } }), state.sections || api("fields")]);
  state.sections = sections;
  const pending = (await uploader.pending(id)).length;
  const genere = Boolean(visit.genere_le);

  render(`${header(visit.titre || "Visite", { back: "#/", actions: `<span class="save-state" id="save"></span>` })}
  <nav class="tabs">${ONGLETS.map(([k, l]) => `<a href="#/visite/${id}/${k}" class="${k === onglet ? "on" : ""}">${l}</a>`).join("")}</nav>
  <main class="page" id="content"></main>`);

  const $c = document.getElementById("content");
  const saver = makeSaver(id);
  cleanup = () => saver.flush();

  // Visite pas encore générée : on propose de créer la fiche (la fiche reste consultable si des informations ont été dictées)
  const aDesChamps = Object.keys(champsOf(visit)).length > 0;
  if (!genere && onglet !== "audio" && !(onglet === "fiche" && aDesChamps)) {
    const enCours = visit.statut === "generation";
    $c.innerHTML = `<div class="vide">
      ${visit.erreur ? `<p class="erreur">${esc(visit.erreur)}</p>` : ""}
      <p>${pending ? `⏳ ${pending} morceau(x) d'audio encore à envoyer.` : visit.morceaux.length ? "L'enregistrement est prêt." : "Aucun audio enregistré pour cette visite."}</p>
      ${enCours ? `<p class="muted">Une génération est en cours ou a été interrompue.</p>` : ""}
      ${visit.morceaux.length || pending || aDesChamps ? `<button class="btn magic big" id="gen">✨ Créer la fiche</button>` : ""}
      <a class="btn ghost" href="#/continuer/${id}">● ${visit.morceaux.length ? "Reprendre l'enregistrement" : "Enregistrer"}</a>
      <a class="btn" href="#/visite/${id}/dialogue">🎙️ Compléter le dossier à la voix</a>
    </div>`;
    document.getElementById("gen")?.addEventListener("click", () => generate(id));
    return;
  }

  if (onglet === "fiche") renderFiche($c, visit, sections, saver);
  else if (onglet === "annonce") renderAnnonce($c, visit, saver);
  else if (onglet === "rapport") renderTexte($c, visit, saver, "rapport_agent", "Rapport interne", "Pour vous uniquement : avis, risques, points à vérifier.", "rapport");
  else if (onglet === "vendeur") renderVendeur($c, visit, saver);
  else renderAudio($c, visit, pending);
}

/** Sauvegarde automatique, une seconde après la dernière frappe. */
function makeSaver(id) {
  let patch = {};
  let timer = null;
  const $s = () => document.getElementById("save");
  const flush = async () => {
    clearTimeout(timer);
    if (!Object.keys(patch).length) return;
    const body = patch;
    patch = {};
    if ($s()) $s().textContent = "Enregistrement…";
    try {
      await api("visit", { method: "POST", query: { id }, body });
      if ($s()) $s().textContent = "✓ Enregistré";
    } catch (e) {
      patch = { ...body, ...patch, champs: { ...body.champs, ...patch.champs } };
      if ($s()) $s().textContent = "⚠ Non enregistré";
      toast(e.message, "erreur");
    }
  };
  return {
    set(key, value) {
      patch[key] = value;
      this.later();
    },
    setChamp(cle, value) {
      patch.champs = { ...patch.champs, [cle]: value };
      this.later();
    },
    later() {
      if ($s()) $s().textContent = "…";
      clearTimeout(timer);
      timer = setTimeout(flush, 1000);
    },
    flush,
  };
}

function renderFiche($c, visit, sections, saver) {
  const champs = champsOf(visit);
  const nbIa = Object.values(champs).filter((c) => c.source === "ia").length;

  const manquants = new Set((visit.manquants || []).map((m) => m.cle));
  const input = (f) => {
    const val = champs[f.cle]?.valeur ?? "";
    const attrs = `name="${f.cle}" id="c-${f.cle}"`;
    if (f.type === "select" || f.type === "bool") {
      const opts = f.type === "bool" ? ["oui", "non"] : f.options;
      const extra = val && !opts.includes(val) ? [val] : []; // valeur inattendue conservée
      return `<select ${attrs}><option value=""></option>${[...opts, ...extra].map((o) => `<option ${o === val ? "selected" : ""}>${esc(o)}</option>`).join("")}</select>`;
    }
    if (f.type === "textarea") return `<textarea ${attrs} rows="2">${esc(val)}</textarea>`;
    if (f.type === "date") return `<input ${attrs} value="${esc(val)}" placeholder="JJ/MM/AAAA" inputmode="numeric">`;
    return `<input ${attrs} value="${esc(val)}" ${f.type === "number" ? 'inputmode="decimal"' : ""}>`;
  };

  $c.innerHTML = `
    ${carteDossier(visit)}
    ${nbIa ? `<div class="info-ia">✨ ${nbIa} champ(s) rempli(s) par l'IA. Touchez <span class="chip">IA</span> pour voir ce qui a été dit.</div>` : ""}
    ${sections
      .map(
        (s) => `<section class="card">
        <h2>${esc(s.titre)}</h2>
        ${s.champs
          .map((f) => {
            const c = champs[f.cle];
            const chip =
              c?.source === "ia" && c.citation
                ? `<button type="button" class="chip" data-cite="${esc(c.citation)}">IA</button>`
                : c?.source === "dialogue"
                  ? '<span class="chip chip-voix">DICTÉ</span>'
                  : "";
            const manque = manquants.has(f.cle) ? '<span class="manque" title="Obligatoire pour le mandat">●</span>' : "";
            return `<div class="field ${c ? "filled" : ""} ${c?.source === "ia" || c?.source === "dialogue" ? "ia" : ""}">
              <label for="c-${f.cle}">${manque}${esc(f.label)}${f.unite ? ` <span class="muted">(${f.unite})</span>` : ""} ${chip}</label>
              ${input(f)}
            </div>`;
          })
          .join("")}
      </section>`,
      )
      .join("")}
    <div class="card actions-card">
      <button class="btn primary" data-pdf="fiche">📄 PDF de la fiche</button>
      <button class="btn" data-send="fiche">✉️ Envoyer par e-mail</button>
      <button class="btn" data-pdf="dossier">📚 Dossier complet (PDF interne)</button>
      <button class="btn" id="copy-fiche">📋 Copier la fiche</button>
      <button class="btn ghost" id="regen">↻ Régénérer avec l'IA</button>
      <p class="muted small">Vos corrections manuelles sont conservées lors d'une régénération.</p>
    </div>`;

  $c.addEventListener("input", (e) => {
    const el = e.target.closest("[name]");
    if (!el) return;
    saver.setChamp(el.name, el.value);
    const field = el.closest(".field");
    field.classList.remove("ia");
    field.classList.toggle("filled", el.value !== "");
    field.querySelector(".chip")?.remove(); // corrigé par l'agent : ce n'est plus l'IA
  });
  $c.addEventListener("click", (e) => {
    const chip = e.target.closest(".chip[data-cite]");
    if (chip) {
      e.preventDefault();
      toast(`🎙️ « ${chip.dataset.cite} »`);
    }
  });
  document.getElementById("copy-fiche").onclick = () => {
    const lignes = sections.flatMap((s) => [
      `— ${s.titre.toUpperCase()} —`,
      ...s.champs
        .map((f) => [f, document.getElementById(`c-${f.cle}`).value])
        .filter(([, v]) => v)
        .map(([f, v]) => `${f.label} : ${v}${f.unite ? " " + f.unite : ""}`),
      "",
    ]);
    copier(lignes.join("\n"));
  };
  bindDocActions($c, visit, saver);
  document.getElementById("gen-docs")?.addEventListener("click", async () => {
    await saver.flush();
    generate(visit.id);
  });
  document.getElementById("regen").onclick = async () => {
    if (!confirm("Régénérer la fiche, l'annonce et les rapports à partir de l'audio ? Les textes modifiés seront remplacés (les champs corrigés à la main sont conservés).")) return;
    await saver.flush();
    generate(visit.id);
  };
}

function textArea(key, value, rows = 18) {
  return `<textarea class="doc" data-key="${key}" rows="${rows}">${esc(value)}</textarea>`;
}

function bindTextAreas($c, saver, onInput) {
  $c.querySelectorAll("[data-key]").forEach((el) =>
    el.addEventListener("input", () => {
      saver.set(el.dataset.key, el.value);
      onInput?.();
    }),
  );
}

function renderAnnonce($c, visit, saver) {
  $c.innerHTML = `<section class="card">
      <h2>Titre</h2>
      <input class="doc-title" data-key="titre_annonce" value="${esc(visit.titre_annonce)}">
      <h2>Description <span class="muted small" id="count"></span></h2>
      ${textArea("annonce", visit.annonce, 20)}
    </section>
    <div class="sticky-actions">
      <button class="btn primary" data-pdf="annonce">📄 PDF</button>
      <button class="btn" data-send="annonce">✉️ Envoyer</button>
      <button class="btn" id="copy">📋 Copier</button>
    </div>`;
  const count = () => (document.getElementById("count").textContent = `${$c.querySelector('[data-key="annonce"]').value.length} caractères`);
  count();
  bindTextAreas($c, saver, count);
  bindDocActions($c, visit, saver);
  document.getElementById("copy").onclick = () =>
    copier(`${$c.querySelector('[data-key="titre_annonce"]').value}\n\n${$c.querySelector('[data-key="annonce"]').value}`);
}

function renderTexte($c, visit, saver, key, titre, aide, doc) {
  $c.innerHTML = `<section class="card">
      <h2>${titre}</h2>
      <p class="muted small">${aide}</p>
      ${textArea(key, visit[key], 26)}
    </section>
    ${doc === "vendeur" ? historiqueEnvois(visit) : ""}
    <div class="sticky-actions">
      ${doc === "vendeur" ? `<button class="btn primary" data-send="vendeur">✉️ Envoyer</button><button class="btn" data-pdf="vendeur">📄 PDF</button>` : `<button class="btn primary" data-pdf="${doc}">📄 PDF</button><button class="btn" data-send="${doc}">✉️ Envoyer</button>`}
      <button class="btn" id="copy">📋 Copier</button>
    </div>`;
  bindTextAreas($c, saver);
  bindDocActions($c, visit, saver);
  document.getElementById("copy").onclick = () => copier($c.querySelector(`[data-key="${key}"]`).value);
}

function renderVendeur($c, visit, saver) {
  renderTexte($c, visit, saver, "rapport_vendeur", "Compte rendu pour le vendeur", "À relire avant envoi. Ton professionnel, sans remarques internes.", "vendeur");
}

// ---------- Conversation vocale : compléter le dossier ----------

function carteDossier(visit) {
  const pct = visit.completude ?? 0;
  const n = (visit.manquants || []).length;
  return `<section class="card dossier-card">
    <div class="dossier-top"><h2>Dossier & mandat</h2><strong class="pct">${pct} %</strong></div>
    <div class="jauge"><span style="width:${pct}%"></span></div>
    <p class="small muted">${n ? `${n} information${n > 1 ? "s" : ""} obligatoire${n > 1 ? "s" : ""} à compléter (repérées par <span class="manque">●</span>).` : "Toutes les informations obligatoires sont renseignées."}</p>
    ${n ? `<a class="btn magic big" href="#/visite/${visit.id}/dialogue">🎙️ Compléter à la voix</a>` : `<a class="btn" href="#/visite/${visit.id}/dialogue">🎙️ Vérifier à la voix</a>`}
    ${visit.genere_le ? "" : `<button class="btn primary big" id="gen-docs">✨ Créer les documents</button>`}
  </section>`;
}

const ETATS = {
  connexion: ["Connexion…", ""],
  ecoute: ["À vous", "ecoute"],
  agent: ["Je vous écoute", "agent"],
  reflexion: ["…", "reflexion"],
  ia: ["L'assistant parle", "ia"],
  pause: ["En pause", "pause"],
};

async function viewDialogue(id) {
  const visit = await api("visit", { query: { id } });
  let conv = null;
  let noteCount = 0;

  render(`${header("Compléter le dossier", { back: `#/visite/${id}/fiche` })}
  <main class="page dialogue">
    <section class="card dossier-card">
      <div class="dossier-top"><h2>Dossier & mandat</h2><strong class="pct" id="pct">${visit.completude} %</strong></div>
      <div class="jauge"><span id="jauge" style="width:${visit.completude}%"></span></div>
      <p class="small muted" id="reste">${visit.manquants.length} information(s) obligatoire(s) à compléter.</p>
    </section>
    <div id="zone">
      <div class="dlg-intro">
        <span class="tag">Assistant vocal</span>
        <h2>On complète le dossier <mark>à la voix.</mark></h2>
        <p class="muted">L'assistant vous pose uniquement les questions qui manquent : vendeurs, mandat, situation juridique… Répondez naturellement, vous pouvez donner plusieurs informations d'un coup, dire « je ne sais pas » ou lui couper la parole.</p>
        <button class="btn magic big" id="start">🎙️ Démarrer la conversation</button>
        <p class="small muted center">Parlez près du téléphone. Les informations s'enregistrent au fil de l'eau.</p>
      </div>
    </div>
  </main>`);

  const $ = (x) => document.getElementById(x);
  const majDossier = (v) => {
    $("pct").textContent = `${v.completude} %`;
    $("jauge").style.width = `${v.completude}%`;
    $("reste").textContent = v.manquants.length ? `${v.manquants.length} information(s) obligatoire(s) à compléter.` : "Toutes les informations obligatoires sont renseignées ✓";
  };

  const finUsage = (usage) => {
    if (!usage.promptTokensDetails.length && !usage.responseTokensDetails.length) return;
    fetch(`api/?${new URLSearchParams({ r: "usage", id })}`, {
      method: "POST",
      keepalive: true,
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-Requested-With": "visite-immo" },
      body: JSON.stringify({ usage }),
    }).catch(() => {});
  };

  const ecranConversation = () => {
    $("zone").innerHTML = `
      <div class="dlg-scene">
        <div class="orbe" id="orbe"><span></span></div>
        <div class="dlg-etat" id="etat">Connexion…</div>
        <p class="dlg-ia" id="ia"></p>
        <p class="dlg-agent" id="agent"></p>
      </div>
      <div class="notes" id="notes"></div>
      <form class="dlg-ecrire" id="ecrire" hidden><input id="ecrire-txt" placeholder="Écrire une réponse (nom à épeler…)" autocomplete="off"><button class="btn primary">Envoyer</button></form>
      <div class="sticky-actions">
        <button class="btn" id="pause">⏸ Pause</button>
        <button class="btn" id="clavier">⌨️ Écrire</button>
        <button class="btn primary" id="stop">■ Terminer</button>
      </div>`;
    $("pause").onclick = () => ($("pause").textContent = conv.basculerPause() ? "▶ Reprendre" : "⏸ Pause");
    $("clavier").onclick = () => {
      $("ecrire").hidden = !$("ecrire").hidden;
      if (!$("ecrire").hidden) $("ecrire-txt").focus();
    };
    $("ecrire").onsubmit = (e) => {
      e.preventDefault();
      const t = $("ecrire-txt").value.trim();
      if (t) conv.ecrire(t);
      $("ecrire-txt").value = "";
    };
    $("stop").onclick = () => conv.fermer("arret");
  };

  const ecranFin = (raison, resume) => {
    $("zone").innerHTML = `
      <div class="dlg-intro">
        <div class="done-icon">✓</div>
        <h2>${noteCount} information${noteCount > 1 ? "s" : ""} <mark>enregistrée${noteCount > 1 ? "s" : ""}.</mark></h2>
        ${resume ? `<p>${esc(resume)}</p>` : ""}
        ${raison.startsWith("refus") ? `<p class="erreur">${esc(raison)}. Vérifiez le modèle de conversation dans les Paramètres.</p>` : raison === "coupure" ? '<p class="erreur">La connexion a été coupée. Vous pouvez reprendre : les informations déjà dictées sont conservées.</p>' : ""}
        <button class="btn magic big" id="regen">✨ Mettre à jour les documents</button>
        <a class="btn" href="#/visite/${id}/dialogue" id="encore">🎙️ Reprendre la conversation</a>
        <a class="btn ghost" href="#/visite/${id}/fiche">Voir la fiche</a>
      </div>`;
    $("encore").onclick = (e) => {
      e.preventDefault();
      viewDialogue(id);
    };
    $("regen").onclick = () => generate(id);
  };

  let resumeFin = "";
  const lancer = async () => {
    const config = await api("live", { method: "POST", query: { id } });
    conv = new Conversation(config, {
      onState: (etat) => {
        const [label, cls] = ETATS[etat] || [etat, ""];
        if ($("etat")) $("etat").textContent = label;
        if ($("orbe")) $("orbe").className = `orbe ${cls}`;
      },
      onIa: (t) => {
        if ($("ia")) $("ia").textContent = t;
        if ($("agent")) $("agent").textContent = "";
      },
      onAgent: (t) => {
        if ($("agent")) $("agent").textContent = `Vous : ${t}`;
      },
      onInfo: (t) => toast(t),
      onNoter: async (champs) => {
        const valeurs = {};
        for (const c of champs) if (c.cle && c.valeur !== undefined) valeurs[c.cle] = String(c.valeur);
        const v = await api("visit", { method: "POST", query: { id }, body: { champs: valeurs, source: "dialogue" } });
        majDossier(v);
        const labels = Object.fromEntries((state.sections || []).flatMap((s) => s.champs.map((f) => [f.cle, f.label])));
        for (const [k, val] of Object.entries(valeurs)) {
          noteCount++;
          $("notes")?.insertAdjacentHTML("afterbegin", `<span class="note">✓ ${esc(labels[k] || k)} : <strong>${esc(val)}</strong></span>`);
        }
        navigator.vibrate?.(30);
        return { resultat: "noté", encore_obligatoire: v.manquants.map((m) => m.label) };
      },
      onTerminer: (resume) => (resumeFin = resume),
      onUsage: finUsage,
      onRelance: async () => {
        try {
          const c = await api("live", { method: "POST", query: { id } });
          conv.config = { ...c, audio_natif: true };
          conv.connecter();
        } catch (e) {
          ecranFin(`refus : ${e.message}`, "");
        }
      },
      onFin: (raison) => {
        window.onbeforeunload = null;
        ecranFin(raison, resumeFin);
      },
    });
    ecranConversation();
    await conv.demarrer();
    window.onbeforeunload = () => "Conversation en cours";
  };

  state.sections = state.sections || (await api("fields"));
  $("start").onclick = async () => {
    $("start").disabled = true;
    try {
      await lancer();
    } catch (e) {
      toast(e.name === "NotAllowedError" ? "Accès au micro refusé" : e.message, "erreur");
      conv?.fermer("arret");
      viewDialogue(id);
    }
  };
  cleanup = () => {
    if (conv && !conv.ferme) conv.fermer("arret");
  };
}

// ---------- PDF et envoi par e-mail ----------

const DOCS = {
  vendeur: { label: "Compte rendu de visite", interne: false },
  fiche: { label: "Fiche du bien", interne: false },
  annonce: { label: "Annonce", interne: false },
  rapport: { label: "Rapport de visite", interne: true },
  dossier: { label: "Dossier complet", interne: true },
};

const pdfUrl = (id, doc, dl = false) => `api/?${new URLSearchParams({ r: "pdf", id, doc, ...(dl ? { dl: 1 } : {}) })}`;

function bindDocActions($c, visit, saver) {
  $c.querySelectorAll("[data-pdf]").forEach((b) => {
    b.onclick = async () => {
      await saver.flush(); // le PDF reprend les dernières corrections
      window.open(pdfUrl(visit.id, b.dataset.pdf), "_blank");
    };
  });
  $c.querySelectorAll("[data-send]").forEach((b) => {
    b.onclick = async () => {
      await saver.flush();
      openSendSheet(visit, b.dataset.send);
    };
  });
}

function historiqueEnvois(visit) {
  const envois = visit.envois || [];
  if (!envois.length) return "";
  return `<section class="card">
    <h2>Envois</h2>
    ${envois
      .slice()
      .reverse()
      .map((e) => `<div class="envoi"><strong>${esc(e.a)}</strong><span class="muted small">${fmtDate(e.date)} · ${e.docs.map((d) => DOCS[d]?.label || d).join(", ")}${e.copie ? " · copie à moi" : ""}</span></div>`)
      .join("")}
  </section>`;
}

function messageParDefaut(visit, doc) {
  const champs = champsOf(visit);
  const vendeur = champs.nom_vendeur?.valeur || "";
  const bien = visit.titre || champs.adresse?.valeur || "le bien";
  const date = new Date(visit.cree_le).toLocaleDateString("fr-FR", { day: "numeric", month: "long" });
  const signature = `${state.user.nom}${state.agence ? "\n" + state.agence : ""}${state.user.telephone ? "\n" + state.user.telephone : ""}`;
  const corps = {
    vendeur: `Je vous remercie pour votre accueil lors de la visite du ${date}. Vous trouverez ci-joint le compte rendu de cette visite.`,
    fiche: `Vous trouverez ci-joint la fiche détaillée du bien situé ${bien}.`,
    annonce: `Vous trouverez ci-joint la présentation du bien situé ${bien}.`,
    rapport: `Ci-joint le rapport de visite du bien situé ${bien}.`,
    dossier: `Ci-joint le dossier complet de la visite du bien situé ${bien}.`,
  }[doc];
  return `Bonjour${doc === "vendeur" && vendeur ? " " + vendeur : ""},\n\n${corps}\n\nJe reste à votre disposition pour toute question.\n\nBien cordialement,\n${signature}`;
}

function openSendSheet(visit, doc) {
  const champs = champsOf(visit);
  const sheet = document.createElement("div");
  sheet.className = "sheet-bg";
  const fermer = () => sheet.remove();

  if (!state.email) {
    sheet.innerHTML = `<div class="sheet sheet-form"><h2>Envoyer par e-mail</h2>
      <p>L'envoi d'e-mails n'est pas encore configuré.</p>
      ${state.user.role === "admin" ? `<a class="btn primary" href="#/reglages">⚙️ Configurer l'envoi</a>` : `<p class="muted">Demandez à un administrateur de le configurer dans les Paramètres.</p>`}
      <button class="btn ghost" data-close>Fermer</button></div>`;
  } else {
    const destinataire = ["vendeur", "fiche"].includes(doc) ? champs.email_vendeur?.valeur || "" : "";
    const sujet = {
      vendeur: `Compte rendu de visite · ${visit.titre || ""}`,
      fiche: `Fiche du bien · ${visit.titre || ""}`,
      annonce: visit.titre_annonce || `Présentation du bien · ${visit.titre || ""}`,
      rapport: `Rapport de visite (interne) · ${visit.titre || ""}`,
      dossier: `Dossier de visite (interne) · ${visit.titre || ""}`,
    }[doc].replace(/ · $/, "");
    sheet.innerHTML = `<form class="sheet sheet-form" id="send-form">
      <h2>✉️ Envoyer par e-mail</h2>
      <label>Destinataire<input name="to" type="email" required inputmode="email" autocomplete="email" value="${esc(destinataire)}" placeholder="adresse@client.fr"></label>
      <label>Objet<input name="sujet" required value="${esc(sujet)}"></label>
      <label>Message<textarea name="message" rows="8" required>${esc(messageParDefaut(visit, doc))}</textarea></label>
      <fieldset class="pj">
        <legend>Pièces jointes (PDF)</legend>
        ${Object.entries(DOCS)
          .map(([k, d]) => `<label class="check"><input type="checkbox" name="docs" value="${k}" ${k === doc ? "checked" : ""}> ${d.label}${d.interne ? ' <span class="badge rouge">interne</span>' : ""}</label>`)
          .join("")}
      </fieldset>
      <label class="check"><input type="checkbox" name="copie" ${state.user.email ? "checked" : "disabled"}> M'envoyer une copie ${state.user.email ? `<span class="muted small">(${esc(state.user.email)})</span>` : '<span class="muted small">(ajoutez votre e-mail dans Mon compte)</span>'}</label>
      <button class="btn primary big" id="send-btn">Envoyer</button>
      <button type="button" class="btn ghost" data-close>Annuler</button>
    </form>`;
  }
  sheet.addEventListener("click", (e) => (e.target === sheet || e.target.closest("[data-close]") ? fermer() : null));
  document.body.append(sheet);

  const form = sheet.querySelector("#send-form");
  if (!form) return;
  form.onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const docs = fd.getAll("docs");
    if (!docs.length) return toast("Cochez au moins un document à joindre", "erreur");
    const internes = docs.filter((d) => DOCS[d].interne);
    if (internes.length && fd.get("to") !== state.user.email && !confirm(`Attention : ${internes.map((d) => DOCS[d].label).join(" et ")} ${internes.length > 1 ? "sont des documents internes" : "est un document interne"}. L'envoyer quand même à ${fd.get("to")} ?`)) return;
    const btn = sheet.querySelector("#send-btn");
    btn.disabled = true;
    btn.textContent = "Envoi en cours…";
    try {
      const v = await api("send", { method: "POST", query: { id: visit.id }, body: { to: fd.get("to"), sujet: fd.get("sujet"), message: fd.get("message"), docs, copie: fd.has("copie") } });
      visit.envois = v.envois;
      fermer();
      toast(`E-mail envoyé à ${fd.get("to")} ✓`, "ok");
      if (location.hash.endsWith("/vendeur")) route();
    } catch (err) {
      toast(err.message, "erreur");
      btn.disabled = false;
      btn.textContent = "Envoyer";
    }
  };
}

function renderAudio($c, visit, pending) {
  const total = visit.morceaux.reduce((s, m) => s + (m.duree || 0), 0);
  const transcript = visit.morceaux.map((m) => m.transcription).filter(Boolean).join("\n\n");
  $c.innerHTML = `
    <section class="card">
      <h2>Enregistrement <span class="muted small">${fmtDuree(total)} · ${visit.morceaux.length} morceau(x)</span></h2>
      ${pending ? `<p class="orange-txt">⏳ ${pending} morceau(x) encore sur le téléphone, en attente d'envoi.</p>` : ""}
      ${
        visit.audio_supprime
          ? `<p class="muted">L'audio a été supprimé. La transcription est conservée.</p>`
          : visit.morceaux
              .map(
                (m) => `<div class="morceau"><span class="muted small">Partie ${m.n + 1} · ${fmtDuree(m.duree)}${m.statut === "erreur" ? ' · <span class="rouge-txt">non transcrite</span>' : ""}</span>
                <audio controls preload="none" src="${audioUrl(visit.id, m.n)}"></audio></div>`,
              )
              .join("") || `<p class="muted">Aucun audio.</p>`
      }
      <a class="btn ghost" href="#/continuer/${visit.id}">● Ajouter un enregistrement</a>
    </section>
    <section class="card">
      <h2>Transcription</h2>
      <div class="transcript">${esc(transcript) || '<span class="muted">Pas encore de transcription.</span>'}</div>
    </section>
    ${historiqueEnvois(visit)}
    <section class="card danger-zone">
      <h2>Archivage</h2>
      <p class="muted small">Visite créée le ${fmtDate(visit.cree_le)}. Accord du vendeur recueilli le ${fmtDate(visit.consentement_le)}.</p>
      ${visit.audio_supprime || !visit.morceaux.length ? "" : `<button class="btn danger-ghost" id="del-audio">🗑 Supprimer l'audio (garder la fiche)</button>`}
      <button class="btn danger" id="del-visit">🗑 Supprimer toute la visite</button>
    </section>`;

  document.getElementById("del-audio")?.addEventListener("click", async () => {
    if (!confirm("Supprimer définitivement l'audio de cette visite ? La fiche, les rapports et la transcription sont conservés.")) return;
    await api("audio", { method: "DELETE", query: { id: visit.id } });
    toast("Audio supprimé");
    viewVisit(visit.id, "audio");
  });
  document.getElementById("del-visit").onclick = async () => {
    if (!confirm("Supprimer définitivement cette visite (audio, fiche, rapports) ?")) return;
    await api("visit", { method: "DELETE", query: { id: visit.id } });
    toast("Visite supprimée");
    go("/");
  };
}

// ---------- Équipe (admin) ----------

async function viewUsers() {
  render(`${header("Équipe", { back: "#/" })}<main class="page"><div class="loader"></div></main>`);
  const users = await api("users");
  document.querySelector("main").innerHTML = `
    <section class="card">
      <h2>Comptes</h2>
      ${users
        .map(
          (u) => `<div class="user-row"><div><strong>${esc(u.nom)}</strong><div class="muted small">${esc(u.login)} · ${u.role === "admin" ? "Administrateur" : "Agent"}</div></div>
          ${u.id === state.user.id ? '<span class="muted small">vous</span>' : `<button class="btn danger-ghost small-btn" data-del="${u.id}" data-nom="${esc(u.nom)}">Supprimer</button>`}</div>`,
        )
        .join("")}
    </section>
    <form class="card" id="f">
      <h2>Ajouter un agent</h2>
      <label>Nom<input name="nom" required placeholder="Prénom Nom"></label>
      <label>Identifiant<input name="login" required autocapitalize="none"></label>
      <label>E-mail <span class="muted">(facultatif)</span><input name="email" type="email"></label>
      <label>Téléphone <span class="muted">(facultatif)</span><input name="telephone" type="tel"></label>
      <label>Mot de passe provisoire<input name="password" required minlength="8"></label>
      <label class="check"><input type="checkbox" name="role" value="admin"> Administrateur (peut gérer l'équipe)</label>
      <button class="btn primary">Créer le compte</button>
    </form>`;
  document.getElementById("f").onsubmit = async (e) => {
    e.preventDefault();
    try {
      await api("users", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
      toast("Compte créé ✓", "ok");
      viewUsers();
    } catch (err) {
      toast(err.message, "erreur");
    }
  };
  document.querySelectorAll("[data-del]").forEach(
    (b) =>
      (b.onclick = async () => {
        if (!confirm(`Supprimer le compte de ${b.dataset.nom} et toutes ses visites ?`)) return;
        await api("users", { method: "DELETE", query: { id: b.dataset.del } });
        viewUsers();
      }),
  );
}

// ---------- Réglages (admin) ----------

async function viewSettings() {
  render(`${header("Paramètres", { back: "#/" })}<main class="page"><div class="loader"></div></main>`);
  if (state.user.role !== "admin") {
    document.querySelector("main").innerHTML = `<div class="card"><h2>Accès réservé</h2>
      <p>Vous êtes connecté avec le compte <strong>${esc(state.user.login)}</strong> (${esc(state.user.nom)}), qui est un compte <strong>Agent</strong>.</p>
      <p class="muted">Les paramètres (clé Gemini, modèles, stockage, signature) se règlent avec un compte <strong>Administrateur</strong> : le premier compte créé, ou un compte créé avec la case « Administrateur » cochée.</p></div>`;
    return;
  }
  const cfg = await api("settings");
  let modeles = [];

  document.querySelector("main").innerHTML = `
    <form id="f">
      <section class="card">
        <h2>Clé API Gemini</h2>
        <label>Clé API
          <input name="cle" type="password" autocomplete="off" spellcheck="false"
            placeholder="${cfg.cle_configuree ? `Clé enregistrée (${esc(cfg.cle_apercu)})` : "Collez votre clé (AIza…)"}">
        </label>
        <p class="muted small">${cfg.cle_configuree ? "Laissez vide pour garder la clé actuelle. " : ""}Obtenir une clé : <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener"><u>Google AI Studio</u></a></p>
        <button type="button" class="btn" id="load">🔄 Vérifier la clé et charger les modèles</button>
        <p class="small" id="key-state"></p>
      </section>

      <section class="card">
        <h2>Modèles</h2>
        <label>Modèle pour l'analyse <span class="muted">(fiche, annonce, rapports)</span>
          <select name="modele_analyse" id="m-analyse"></select>
        </label>
        <label>Modèle pour la transcription audio
          <select name="modele_transcription" id="m-transcription"></select>
        </label>
        <label>Modèle pour la conversation vocale <span class="muted">(« Compléter à la voix »)</span>
          <select name="modele_dialogue" id="m-dialogue"></select>
        </label>
        <p class="muted small" id="m-desc"></p>
        <p class="muted small">Conseil : un modèle « Flash » suffit pour la transcription (rapide et économique) ; un modèle « Pro » rédige de meilleurs textes pour l'analyse. Pour la conversation, préférez un modèle <strong>sans « native-audio »</strong> dans son nom : il répond en texte, lu gratuitement par la voix du téléphone (les « native-audio » répondent avec la voix Gemini, environ 5 fois plus cher).</p>
      </section>

      <section class="card">
        <h2>Coût de l'IA · ${esc(cfg.couts.mois)}</h2>
        <p class="pct">≈ ${Number(cfg.couts.total).toLocaleString("fr-FR", { maximumFractionDigits: 2 })} €</p>
        <p class="small muted">${Object.entries(cfg.couts.par_type).map(([k, v]) => `${esc(k)} : ${Number(v).toLocaleString("fr-FR", { maximumFractionDigits: 2 })} €`).join(" · ") || "Aucune dépense ce mois-ci."}</p>
        ${Object.keys(cfg.couts.par_agent).length ? `<p class="small muted">${Object.entries(cfg.couts.par_agent).map(([k, v]) => `${esc(k)} : ${Number(v).toLocaleString("fr-FR", { maximumFractionDigits: 2 })} €`).join(" · ")}</p>` : ""}
        <p class="small muted">Estimation calculée à partir des jetons consommés et des tarifs publics de Gemini ; la facture Google fait foi.</p>
      </section>

      <section class="card">
        <h2>Stockage</h2>
        <label>Dossier de stockage
          <input name="data_dir" value="${esc(cfg.data_dir)}" spellcheck="false" autocapitalize="none">
        </label>
        <p class="muted small">Chemin absolu, ou relatif au dossier de l'appli. Actuellement : <code>${esc(cfg.data_dir_absolu)}</code>.
          Si vous le changez, les comptes et les visites y sont déplacés automatiquement. Il ne doit pas être dans <code>public/</code>.</p>
      </section>

      <section class="card">
        <h2>Identité de l'agence</h2>
        <label>Nom de l'agence
          <input name="agence" value="${esc(cfg.agence)}" required>
        </label>
        <p class="muted small">Il signe le compte rendu vendeur : « <span id="sig"></span> ».</p>
        <label>Coordonnées <span class="muted">(en-tête des PDF et pied des e-mails)</span>
          <textarea name="agence_coordonnees" rows="3" placeholder="Adresse&#10;Téléphone · e-mail&#10;Carte professionnelle">${esc(cfg.agence_coordonnees)}</textarea>
        </label>
        <details class="aide"><summary>Mentions légales (obligatoires sur le mandat)</summary>
          ${[
            ["raison_sociale", "Raison sociale (titulaire de la carte)"],
            ["siege", "Adresse du siège"],
            ["siret", "SIRET"],
            ["carte_numero", "Carte professionnelle n°"],
            ["carte_delivree_par", "Délivrée par (CCI)"],
            ["garant", "Garant financier (nom et adresse)"],
            ["rcp", "Assurance responsabilité civile professionnelle"],
          ]
            .map(([k, l]) => `<label>${l}<input name="${k}" value="${esc(cfg.agence_legal[k] || "")}"></label>`)
            .join("")}
        </details>
        <div class="logo-zone">
          <span class="label-like">Logo <span class="muted">(par défaut : logo Synapse ; déposez un PNG ou JPG pour le remplacer)</span></span>
          <div class="logo-preview" id="logo-preview">${cfg.logo ? `<img src="api/?r=logo&t=${Date.now()}" alt="Logo">` : '<img src="img/synapse-logo.svg" alt="Synapse">'}</div>
          <div class="logo-actions">
            <label class="btn"><span id="logo-label">📤 ${cfg.logo ? "Changer le logo" : "Ajouter le logo"}</span><input type="file" id="logo-file" accept="image/png,image/jpeg,image/webp" hidden></label>
            <button type="button" class="btn danger-ghost" id="logo-del" ${cfg.logo ? "" : "hidden"}>Retirer</button>
          </div>
        </div>
      </section>

      <section class="card">
        <h2>Envoi des e-mails</h2>
        <label>Méthode d'envoi
          <select name="email_methode" id="email-methode">
            <option value="" ${!cfg.email_methode ? "selected" : ""}>Désactivé</option>
            <option value="smtp" ${cfg.email_methode === "smtp" ? "selected" : ""}>Serveur SMTP (recommandé)</option>
            <option value="mail" ${cfg.email_methode === "mail" ? "selected" : ""}>Fonction mail() de l'hébergeur</option>
          </select>
        </label>
        <div id="email-fields">
          <label>Adresse de l'expéditeur<input name="email_expediteur" type="email" value="${esc(cfg.email_expediteur)}" placeholder="contact@votre-agence.fr"></label>
          <label>Nom de l'expéditeur<input name="email_expediteur_nom" value="${esc(cfg.email_expediteur_nom)}" placeholder="${esc(cfg.agence)}"></label>
          <p class="muted small">Les réponses des clients arrivent directement chez l'agent qui a envoyé le document (son e-mail, défini dans « Mon compte »).</p>
        </div>
        <div id="smtp-fields">
          <div class="row-2">
            <label>Serveur SMTP<input name="smtp_host" value="${esc(cfg.smtp_host)}" placeholder="ssl0.ovh.net" autocapitalize="none" spellcheck="false"></label>
            <label>Port<input name="smtp_port" type="number" value="${esc(cfg.smtp_port)}" inputmode="numeric"></label>
          </div>
          <label>Sécurité
            <select name="smtp_securite">
              <option value="ssl" ${cfg.smtp_securite === "ssl" ? "selected" : ""}>SSL (port 465)</option>
              <option value="tls" ${cfg.smtp_securite === "tls" ? "selected" : ""}>STARTTLS (port 587)</option>
              <option value="aucune" ${cfg.smtp_securite === "aucune" ? "selected" : ""}>Aucune (déconseillé)</option>
            </select>
          </label>
          <label>Identifiant<input name="smtp_user" value="${esc(cfg.smtp_user)}" autocomplete="off" autocapitalize="none" spellcheck="false"></label>
          <label>Mot de passe<input name="smtp_pass" type="password" autocomplete="new-password" placeholder="${cfg.smtp_pass_configure ? "Enregistré : laisser vide pour le garder" : ""}"></label>
          <details class="aide"><summary>Réglages courants</summary>
            <p class="small"><strong>OVH</strong> : ssl0.ovh.net · 465 · SSL · identifiant = adresse e-mail complète<br>
            <strong>o2switch</strong> : mail.votre-domaine.fr · 465 · SSL<br>
            <strong>Gmail / Google Workspace</strong> : smtp.gmail.com · 465 · SSL · mot de passe d'application<br>
            <strong>Microsoft 365</strong> : smtp.office365.com · 587 · STARTTLS</p>
          </details>
        </div>
        <div class="test-row" id="test-row">
          <input id="test-to" type="email" placeholder="Votre adresse pour le test" value="${esc(state.user.email || "")}">
          <button type="button" class="btn" id="test-btn">Envoyer un test</button>
        </div>
        <p class="muted small" id="test-hint">Enregistrez d'abord les paramètres, puis envoyez-vous un e-mail de test.</p>
      </section>

      <button class="btn primary big">Enregistrer les paramètres</button>
      <div class="spacer"></div>
    </form>`;

  const $ = (id) => document.getElementById(id);
  const form = $("f");
  const updateSig = () => ($("sig").textContent = `${state.user.nom}, ${form.agence.value || "…"}`);
  const majEmail = () => {
    const m = $("email-methode").value;
    $("email-fields").hidden = !m;
    $("smtp-fields").hidden = m !== "smtp";
    $("test-row").hidden = $("test-hint").hidden = !m;
  };
  $("email-methode").onchange = majEmail;
  majEmail();
  $("test-btn").onclick = async () => {
    const to = $("test-to").value.trim();
    if (!to) return toast("Indiquez une adresse pour le test", "erreur");
    $("test-btn").disabled = true;
    $("test-btn").textContent = "Envoi…";
    try {
      await api("mailtest", { method: "POST", body: { to } });
      toast(`E-mail de test envoyé à ${to} ✓`, "ok");
    } catch (e) {
      toast(e.message, "erreur");
    } finally {
      $("test-btn").disabled = false;
      $("test-btn").textContent = "Envoyer un test";
    }
  };
  // Le logo s'enregistre tout de suite, sans recharger l'écran (les autres champs saisis restent en place)
  const afficherLogo = (present) => {
    $("logo-preview").innerHTML = present
      ? `<img src="api/?r=logo&t=${Date.now()}" alt="Logo">`
      : '<img src="img/synapse-logo.svg" alt="Synapse">';
    $("logo-label").textContent = present ? "📤 Changer le logo" : "📤 Ajouter le logo";
    $("logo-del").hidden = !present;
  };
  $("logo-file").onchange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append("logo", file);
    try {
      await api("logo", { method: "POST", form: fd });
      afficherLogo(true);
      appliquerTheme({ logo: true, logoV: Date.now() });
      toast("Logo enregistré ✓", "ok");
    } catch (err) {
      toast(err.message, "erreur");
    }
    e.target.value = "";
  };
  $("logo-del").addEventListener("click", async () => {
    if (!confirm("Retirer le logo ?")) return;
    await api("logo", { method: "DELETE" });
    afficherLogo(false);
    appliquerTheme({ logo: false });
  });
  updateSig();
  form.agence.addEventListener("input", updateSig);

  // Remplit les deux listes ; le modèle actuel reste sélectionné même s'il n'est pas (ou plus) proposé
  const fillSelects = () => {
    for (const [id, actuel, filtre] of [
      ["m-analyse", form.modele_analyse.value || cfg.modele_analyse, (m) => m.generation !== false],
      ["m-transcription", form.modele_transcription.value || cfg.modele_transcription, (m) => m.generation !== false],
      ["m-dialogue", form.modele_dialogue.value || cfg.modele_dialogue, (m) => m.live],
    ]) {
      const liste = modeles.filter(filtre).sort((a, b) => (a.audio_natif || false) - (b.audio_natif || false));
      const ids = liste.map((m) => m.id);
      const options = ids.includes(actuel) || !actuel ? liste : [{ id: actuel, nom: `${actuel} (actuel)` }, ...liste];
      $(id).innerHTML = options.map((m) => `<option value="${esc(m.id)}" ${m.id === actuel ? "selected" : ""}>${esc(m.nom)}${m.nom !== m.id ? ` · ${esc(m.id)}` : ""}${m.audio_natif ? " · voix Gemini (plus cher)" : ""}</option>`).join("");
    }
    showDesc();
  };
  const showDesc = () => {
    const desc = (id) => modeles.find((m) => m.id === $(id).value)?.description;
    $("m-desc").textContent = [desc("m-analyse") && `Analyse : ${desc("m-analyse")}`, desc("m-transcription") && `Transcription : ${desc("m-transcription")}`]
      .filter(Boolean).join("\n");
  };
  $("m-analyse").onchange = showDesc;
  $("m-transcription").onchange = showDesc;
  $("m-dialogue").onchange = showDesc;

  const loadModels = async () => {
    const cle = form.cle.value.trim();
    if (!cle && !cfg.cle_configuree) {
      $("key-state").innerHTML = `<span class="muted">Saisissez votre clé pour voir la liste des modèles.</span>`;
      return;
    }
    $("load").disabled = true;
    $("key-state").innerHTML = `<span class="muted">Vérification…</span>`;
    try {
      modeles = await api("models", { method: "POST", body: { cle } });
      $("key-state").innerHTML = `<span class="vert-txt">✓ Clé valide : ${modeles.length} modèles disponibles.</span>`;
      fillSelects();
    } catch (e) {
      $("key-state").innerHTML = `<span class="erreur">✗ ${esc(e.message)}</span>`;
    } finally {
      $("load").disabled = false;
    }
  };
  $("load").onclick = loadModels;
  // Clé collée : on vérifie tout de suite et on charge la liste
  form.cle.addEventListener("change", () => form.cle.value.trim() && loadModels());

  fillSelects(); // affiche au moins les modèles actuels
  if (cfg.cle_configuree) loadModels();

  form.onsubmit = async (e) => {
    e.preventDefault();
    const btn = form.querySelector("button.primary");
    btn.disabled = true;
    try {
      const body = Object.fromEntries(new FormData(form));
      const res = await api("settings", { method: "POST", body });
      state.demo = !res.cle_configuree;
      state.email = res.email_configure;
      state.agence = res.agence;
      appliquerTheme({ logo: res.logo, agence: res.agence });
      toast("Paramètres enregistrés ✓", "ok");
      viewSettings();
    } catch (err) {
      toast(err.message, "erreur");
      btn.disabled = false;
    }
  };
}

function viewAccount() {
  const u = state.user;
  render(`${header("Mon compte", { back: "#/" })}<main class="page">
    <form class="card" id="profil">
      <h2>Mes coordonnées</h2>
      <p class="muted small">Elles apparaissent sur les PDF (encadré contact, signature). Votre e-mail reçoit les réponses des clients.</p>
      <label>Nom<input name="nom" required value="${esc(u.nom)}"></label>
      <label>E-mail<input name="email" type="email" value="${esc(u.email)}" inputmode="email" autocomplete="email"></label>
      <label>Téléphone<input name="telephone" type="tel" value="${esc(u.telephone)}" autocomplete="tel"></label>
      <button class="btn primary">Enregistrer</button>
    </form>
    <form class="card" id="f">
      <h2>Changer mon mot de passe</h2>
      <label>Mot de passe actuel<input name="ancien" type="password" required autocomplete="current-password"></label>
      <label>Nouveau mot de passe<input name="nouveau" type="password" required minlength="8" autocomplete="new-password"></label>
      <button class="btn">Changer le mot de passe</button>
    </form></main>`);
  document.getElementById("profil").onsubmit = async (e) => {
    e.preventDefault();
    try {
      state.user = await api("profile", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
      toast("Coordonnées enregistrées ✓", "ok");
    } catch (err) {
      toast(err.message, "erreur");
    }
  };
  document.getElementById("f").onsubmit = async (e) => {
    e.preventDefault();
    try {
      await api("password", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
      toast("Mot de passe modifié ✓", "ok");
      e.target.reset();
    } catch (err) {
      toast(err.message, "erreur");
    }
  };
}

function appliquerTheme(t) {
  Object.assign(theme, t);
}

// ---------- Démarrage ----------

(async function init() {
  if ("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js").catch(() => {});
  if (location.search.includes("maj=")) history.replaceState(null, "", location.pathname + location.hash); // nettoie l'URL après une mise à jour
  try {
    const s = await api("status");
    // Le serveur a une version plus récente que l'interface affichée : on recharge une fois
    if (s.version && s.version !== APP_VERSION) {
      let dejaFait = false;
      try {
        dejaFait = sessionStorage.getItem("reload-version") === s.version;
        sessionStorage.setItem("reload-version", s.version);
      } catch {
        /* stockage indisponible */
      }
      if (!dejaFait) {
        const regs = (await navigator.serviceWorker?.getRegistrations?.()) || [];
        await Promise.all(regs.map((r) => r.update().catch(() => {})));
        location.replace(location.pathname + "?maj=" + Date.now() + location.hash);
        return;
      }
    }
    state.user = s.user;
    state.setup = s.setup;
    state.demo = s.demo;
    state.email = s.email;
    state.agence = s.agence;
  } catch (e) {
    render(`<main class="page"><p class="erreur center">${esc(e.message)}</p><button class="btn" onclick="location.reload()">Réessayer</button></main>`);
    return;
  }
  if (state.user) uploader.run(); // renvoie les morceaux restés en attente
  route();
})();
