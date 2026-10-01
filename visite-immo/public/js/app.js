// Appli Visite Immo : navigation, écrans et logique d'interface.

import { api, audioUrl } from "./api.js";
import { Recorder, recordingSupported } from "./recorder.js";
import { uploader } from "./uploader.js";

const $app = document.getElementById("app");
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

function header(titre, { back = null, actions = "" } = {}) {
  return `<header class="bar">
    ${back ? `<a class="icon-btn" href="${back}" aria-label="Retour">←</a>` : `<span class="logo">🏠</span>`}
    <h1>${esc(titre)}</h1>
    <div class="bar-actions">${actions}</div>
  </header>`;
}

function demoBanner() {
  const d = state.demo;
  if (!d || (!d.transcription && !d.analyse)) return "";
  return `<div class="banner">Mode démo : ${[d.transcription && "transcription", d.analyse && "analyse IA"].filter(Boolean).join(" et ")} simulée(s). Ajoutez les clés API dans <code>app/config.php</code>.</div>`;
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
    if (page === "visite") return viewVisit(id, hash.split("/")[3] || "fiche");
    if (page === "equipe") return viewUsers();
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
    <div class="login-logo">🏠🎙️</div>
    <h1>Visite Immo</h1>
    <p class="muted">${setup ? "Première utilisation : créez le compte administrateur." : "Enregistrez vos visites, l'IA rédige la fiche."}</p>
    <form id="f" class="card">
      ${setup ? `<label>Votre nom<input name="nom" required autocomplete="name" placeholder="Prénom Nom"></label>` : ""}
      <label>Identifiant<input name="login" required autocapitalize="none" autocomplete="username"></label>
      <label>Mot de passe<input name="password" type="password" required autocomplete="${setup ? "new-password" : "current-password"}" minlength="${setup ? 8 : 1}"></label>
      <button class="btn primary big">${setup ? "Créer le compte" : "Se connecter"}</button>
      <p class="erreur" id="err"></p>
    </form>
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
  render(`${header("Mes visites", { actions: menuButton() })}<main class="page"><div class="loader"></div></main>`);
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

  document.querySelector("main").innerHTML = `${demoBanner()}${items}<div class="spacer"></div>`;
  document.querySelector("main").insertAdjacentHTML("afterend", `<a class="fab" href="#/nouvelle"><span class="dot"></span> Nouvelle visite</a>`);
}

function menuButton() {
  return `<button class="icon-btn" id="menu-btn" aria-label="Menu">☰</button>`;
}

function bindMenu() {
  document.getElementById("menu-btn").onclick = () => {
    const sheet = document.createElement("div");
    sheet.className = "sheet-bg";
    sheet.innerHTML = `<div class="sheet">
      <div class="sheet-user">${esc(state.user.nom)}<span class="muted"> · ${state.user.role === "admin" ? "Administrateur" : "Agent"}</span></div>
      ${state.user.role === "admin" ? `<a href="#/equipe">👥 Gérer l'équipe</a>` : ""}
      <a href="#/compte">🔑 Changer mon mot de passe</a>
      <button id="logout">↪ Se déconnecter</button>
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

  // Visite pas encore générée : on propose de créer la fiche
  if (!genere && onglet !== "audio") {
    const enCours = visit.statut === "generation";
    $c.innerHTML = `<div class="vide">
      ${visit.erreur ? `<p class="erreur">${esc(visit.erreur)}</p>` : ""}
      <p>${pending ? `⏳ ${pending} morceau(x) d'audio encore à envoyer.` : visit.morceaux.length ? "L'enregistrement est prêt." : "Aucun audio enregistré pour cette visite."}</p>
      ${enCours ? `<p class="muted">Une génération est en cours ou a été interrompue.</p>` : ""}
      ${visit.morceaux.length || pending ? `<button class="btn magic big" id="gen">✨ Créer la fiche</button>` : ""}
      <a class="btn ghost" href="#/continuer/${id}">● ${visit.morceaux.length ? "Reprendre l'enregistrement" : "Enregistrer"}</a>
    </div>`;
    document.getElementById("gen")?.addEventListener("click", () => generate(id));
    return;
  }

  if (onglet === "fiche") renderFiche($c, visit, sections, saver);
  else if (onglet === "annonce") renderAnnonce($c, visit, saver);
  else if (onglet === "rapport") renderTexte($c, visit, saver, "rapport_agent", "Rapport interne", "Pour vous uniquement : avis, risques, points à vérifier.");
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

  const input = (f) => {
    const val = champs[f.cle]?.valeur ?? "";
    const attrs = `name="${f.cle}" id="c-${f.cle}"`;
    if (f.type === "select" || f.type === "bool") {
      const opts = f.type === "bool" ? ["oui", "non"] : f.options;
      const extra = val && !opts.includes(val) ? [val] : []; // valeur inattendue conservée
      return `<select ${attrs}><option value=""></option>${[...opts, ...extra].map((o) => `<option ${o === val ? "selected" : ""}>${esc(o)}</option>`).join("")}</select>`;
    }
    if (f.type === "textarea") return `<textarea ${attrs} rows="2">${esc(val)}</textarea>`;
    return `<input ${attrs} value="${esc(val)}" ${f.type === "number" ? 'inputmode="decimal"' : ""}>`;
  };

  $c.innerHTML = `
    <div class="info-ia">✨ ${nbIa} champ(s) rempli(s) par l'IA. Touchez <span class="chip">IA</span> pour voir ce qui a été dit.</div>
    ${sections
      .map(
        (s) => `<section class="card">
        <h2>${esc(s.titre)}</h2>
        ${s.champs
          .map((f) => {
            const c = champs[f.cle];
            const chip = c?.source === "ia" && c.citation ? `<button type="button" class="chip" data-cite="${esc(c.citation)}">IA</button>` : "";
            return `<div class="field ${c ? "filled" : ""} ${c?.source === "ia" ? "ia" : ""}">
              <label for="c-${f.cle}">${esc(f.label)}${f.unite ? ` <span class="muted">(${f.unite})</span>` : ""} ${chip}</label>
              ${input(f)}
            </div>`;
          })
          .join("")}
      </section>`,
      )
      .join("")}
    <div class="card actions-card">
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
      <button class="btn primary" id="copy">📋 Copier l'annonce</button>
    </div>`;
  const count = () => (document.getElementById("count").textContent = `${$c.querySelector('[data-key="annonce"]').value.length} caractères`);
  count();
  bindTextAreas($c, saver, count);
  document.getElementById("copy").onclick = () =>
    copier(`${$c.querySelector('[data-key="titre_annonce"]').value}\n\n${$c.querySelector('[data-key="annonce"]').value}`);
}

function renderTexte($c, visit, saver, key, titre, aide) {
  $c.innerHTML = `<section class="card">
      <h2>${titre}</h2>
      <p class="muted small">${aide}</p>
      ${textArea(key, visit[key], 26)}
    </section>
    <div class="sticky-actions">
      <button class="btn" id="copy">📋 Copier</button>
      <button class="btn" id="print">🖨 PDF</button>
    </div>`;
  bindTextAreas($c, saver);
  const value = () => $c.querySelector(`[data-key="${key}"]`).value;
  document.getElementById("copy").onclick = () => copier(value());
  document.getElementById("print").onclick = () => imprimer(`${titre} · ${visit.titre || ""}`, value());
}

function renderVendeur($c, visit, saver) {
  const champs = champsOf(visit);
  const email = champs.email_vendeur?.valeur || "";
  renderTexte($c, visit, saver, "rapport_vendeur", "Compte rendu pour le vendeur", "À relire avant envoi. Ton professionnel, sans remarques internes.");
  document.querySelector(".sticky-actions").insertAdjacentHTML(
    "afterbegin",
    `<button class="btn primary" id="send">✉️ Envoyer</button>`,
  );
  document.getElementById("send").onclick = async () => {
    await saver.flush();
    const texte = $c.querySelector('[data-key="rapport_vendeur"]').value;
    const sujet = `Compte rendu de visite${visit.titre ? " · " + visit.titre : ""}`;
    if (navigator.share && !email) {
      try {
        return await navigator.share({ title: sujet, text: texte });
      } catch {
        /* partage annulé : on tente l'e-mail */
      }
    }
    location.href = `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(sujet)}&body=${encodeURIComponent(texte)}`;
  };
}

function imprimer(titre, texte) {
  const w = window.open("", "_blank");
  if (!w) return toast("Autorisez les fenêtres pour générer le PDF", "erreur");
  w.document.write(`<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>${esc(titre)}</title>
    <style>body{font:12pt/1.55 Georgia,serif;max-width:700px;margin:40px auto;padding:0 20px;color:#111}h1{font:600 15pt system-ui,sans-serif;margin-bottom:24px}pre{white-space:pre-wrap;font:inherit}</style>
    </head><body><h1>${esc(titre)}</h1><pre>${esc(texte)}</pre><script>onload=()=>print()<\/script></body></html>`);
  w.document.close();
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

function viewAccount() {
  render(`${header("Mon compte", { back: "#/" })}<main class="page">
    <form class="card" id="f">
      <h2>Changer mon mot de passe</h2>
      <label>Mot de passe actuel<input name="ancien" type="password" required autocomplete="current-password"></label>
      <label>Nouveau mot de passe<input name="nouveau" type="password" required minlength="8" autocomplete="new-password"></label>
      <button class="btn primary">Enregistrer</button>
    </form></main>`);
  document.getElementById("f").onsubmit = async (e) => {
    e.preventDefault();
    try {
      await api("password", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
      toast("Mot de passe modifié ✓", "ok");
      go("/");
    } catch (err) {
      toast(err.message, "erreur");
    }
  };
}

// ---------- Démarrage ----------

(async function init() {
  if ("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js").catch(() => {});
  try {
    const s = await api("status");
    state.user = s.user;
    state.setup = s.setup;
    state.demo = s.demo;
  } catch (e) {
    render(`<main class="page"><p class="erreur center">${esc(e.message)}</p><button class="btn" onclick="location.reload()">Réessayer</button></main>`);
    return;
  }
  if (state.user) uploader.run(); // renvoie les morceaux restés en attente
  route();
})();
