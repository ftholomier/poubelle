// Au quotidien : briefing du matin à écouter, commande vocale, bilan d'appel dicté, tâches cochables,
// notifications sur le téléphone, tableau de bord (honoraires, palier, temps gagné).

import { api } from "../api.js";
import { dicter } from "../dictee.js";
import { blocsAujourdhui } from "./aujourdhui.js";
import { vues, ecran, esc, toast, go, feuille, fmtEuros, state } from "../ui.js";

// ---------- Briefing à écouter ----------

function voixFr() {
  const voix = speechSynthesis.getVoices().filter((v) => v.lang?.startsWith("fr"));
  return voix.find((v) => /Amélie|Audrey|Thomas|Google/i.test(v.name)) || voix[0] || null;
}

export async function ecouterBriefing() {
  const sheet = feuille(`<h2>☀️ Votre briefing</h2><div class="loader"></div><p class="texte" id="b-texte"></p>
    <div class="btn-row"><button class="btn" id="b-pause" hidden>⏸ Pause</button></div><button class="btn ghost" data-close>Fermer</button>`, { onClose: () => speechSynthesis?.cancel() });
  try {
    const { texte } = await api("briefing");
    sheet.querySelector(".loader")?.remove();
    sheet.querySelector("#b-texte").innerHTML = esc(texte).replace(/\n/g, "<br>");
    if (!("speechSynthesis" in window)) return;
    speechSynthesis.cancel();
    const phrases = texte.split(/\n+/).filter(Boolean);
    for (const p of phrases) {
      const u = new SpeechSynthesisUtterance(p);
      u.lang = "fr-FR";
      u.rate = 1.03;
      const v = voixFr();
      if (v) u.voice = v;
      speechSynthesis.speak(u);
    }
    const b = sheet.querySelector("#b-pause");
    b.hidden = false;
    b.onclick = () => {
      if (speechSynthesis.paused) { speechSynthesis.resume(); b.textContent = "⏸ Pause"; } else { speechSynthesis.pause(); b.textContent = "▶ Reprendre"; }
    };
  } catch (e) {
    toast(e.message, "erreur");
    sheet.fermer();
  }
}

// ---------- Commande vocale ----------

export async function commandeVocale() {
  const r = await dicter("commande", {
    titre: "Dites-moi",
    aide: "Un rendez-vous, une tâche, relancer un vendeur, envoyer le point de la semaine, le bilan d'un appel…",
    exemple: "Ajoute une estimation jeudi 14 h 30 chez monsieur Bernard.",
  });
  if (!r) return;
  const p = r.resultat;
  if (p.intention === "inconnu") return toast("Je n'ai pas compris, pouvez-vous reformuler ?", "erreur");
  if (p.intention === "briefing") return ecouterBriefing();
  if (p.intention === "ouvrir") return go(p.dossier ? `/visite/${p.dossier}/resume` : "/biens");
  const sheet = feuille(`<h2>${esc(p.libelle)}</h2><p class="texte">${esc(p.confirmation)}</p>${p.bien ? `<p class="small muted">Bien : ${esc(p.bien)}</p>` : ""}
    <button class="btn magic big" id="c-ok">✓ C'est ça, vas-y</button><button class="btn ghost" data-close>Annuler</button>`);
  sheet.querySelector("#c-ok").onclick = async (e) => {
    e.currentTarget.disabled = true;
    try {
      const x = await api("commande", { method: "POST", body: { intention: p.intention, dossier: p.dossier, params: p.params } });
      sheet.fermer();
      if (x.message) toast(x.message, "ok");
      if (x.lien && x.lien !== "#/") go(x.lien.slice(1));
      else vues.aujourdhui();
    } catch (err) {
      toast(err.message, "erreur");
      e.currentTarget.disabled = false;
    }
  };
}

export async function bilanAppel() {
  const r = await dicter("appel", {
    titre: "Bilan d'appel",
    aide: "Avec qui, à propos de quel bien, ce qui a été dit, quand rappeler.",
    exemple: "J'ai eu madame Martin, elle accepte de baisser à 399 000 si pas d'offre sous 15 jours, je la rappelle jeudi 10 heures.",
  });
  if (!r) return;
  feuille(`<h2>✓ Appel rangé</h2><p class="texte">${esc(r.donnees.resume)}</p><ul class="prets">${(r.resultat.faits || []).map((f) => `<li>✓ ${esc(f)}</li>`).join("")}</ul><button class="btn primary big" data-close>OK</button>`, { onClose: () => vues.aujourdhui() });
}

// ---------- Notifications ----------

async function activerNotifications() {
  try {
    const perm = await Notification.requestPermission();
    if (perm !== "granted") return toast("Notifications refusées : modifiable dans les réglages du téléphone.", "erreur");
    const reg = await navigator.serviceWorker.ready;
    const { cle } = await api("push_cle");
    const bin = Uint8Array.from(atob(cle.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0));
    const abonnement = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bin });
    await api("push_abonnement", { method: "POST", body: { abonnement: abonnement.toJSON() } });
    await api("push_test", { method: "POST" });
    toast("Notifications activées ✓", "ok");
    vues.aujourdhui();
  } catch (e) {
    toast(`Notifications impossibles : ${e.message}`, "erreur");
  }
}

// ---------- Blocs de l'écran Aujourd'hui ----------

blocsAujourdhui.push({
  html: () => {
    const pushPossible = "PushManager" in window && "Notification" in window && Notification.permission === "default";
    return `<div class="ajd-actions">
      <button class="ajd-bouton" id="ajd-briefing"><span>☀️</span>Mon briefing</button>
      <button class="ajd-bouton citron" id="ajd-commande"><span>🎙️</span>Dites-moi</button>
      <button class="ajd-bouton" id="ajd-appel"><span>📞</span>Bilan d'appel</button>
    </div>
    ${pushPossible ? '<button class="btn ghost" id="ajd-push">🔔 Activer les notifications sur ce téléphone</button>' : ""}`;
  },
  bind: ($m) => {
    $m.querySelector("#ajd-briefing").onclick = ecouterBriefing;
    $m.querySelector("#ajd-commande").onclick = commandeVocale;
    $m.querySelector("#ajd-appel").onclick = bilanAppel;
    $m.querySelector("#ajd-push")?.addEventListener("click", activerNotifications);
    $m.querySelectorAll("[data-tache]").forEach((c) => (c.onclick = async (e) => {
      e.preventDefault();
      e.stopPropagation();
      await api("tache", { method: "POST", query: { id: c.dataset.tache }, body: { fait: true } });
      c.closest(".ajd-item").classList.add("fait");
      toast("Fait ✓", "ok");
    }));
    $m.querySelectorAll("[data-suppr-tache]").forEach((c) => (c.onclick = async (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!confirm("Supprimer cette tâche ?")) return;
      await api("tache", { method: "DELETE", query: { id: c.dataset.supprTache } });
      c.closest(".ajd-item").remove();
      toast("Tâche supprimée");
    }));
    if (location.search.includes("briefing=1")) {
      history.replaceState(null, "", location.pathname + location.hash);
      ecouterBriefing();
    }
  },
});

// ---------- Tableau de bord ----------

const heures = (min) => (min >= 60 ? `${Math.floor(min / 60)} h ${String(min % 60).padStart(2, "0")}` : `${min} min`);

vues.tableau = async () => {
  const $m = ecran("Mon tableau de bord", '<div class="loader"></div>', { back: "#/" });
  const d = await api("tableau");
  const t = d.taux;
  const max = d.paliers.at(-1)[0] * 1.25;
  const pct = Math.min(100, (d.ca_12_mois / max) * 100);
  $m.innerHTML = `
    <section class="card">
      <h2>Honoraires HT · 12 mois glissants</h2>
      <div class="chiffres"><div class="chiffre noir"><small>Chiffre d'affaires</small><strong>${fmtEuros(d.ca_12_mois)}</strong><span class="small">${d.ventes} vente(s)</span></div>
        <div class="chiffre citron"><small>Votre part actuelle</small><strong>${t.taux} %</strong><span class="small">palier ${t.palier}</span></div></div>
      <div class="paliers-barre"><span style="width:${pct}%"></span>${d.paliers.map(([s, taux]) => `<i style="left:${(s / max) * 100}%"><b>${taux} %</b>${s ? `${Math.round(s / 1000)} k€` : "0"}</i>`).join("")}</div>
      <p class="small">${t.prochain_seuil ? `Encore <strong>${fmtEuros(Math.max(0, t.prochain_seuil - d.ca_12_mois))}</strong> d'honoraires pour passer à <strong>${t.prochain_taux} %</strong> sur toutes vos ventes suivantes.` : "Vous êtes au palier maximum : 🎉"}</p>
      ${d.potentiel_ht ? `<p class="small muted">En cours (offres acceptées et compromis) : ${fmtEuros(d.potentiel_ht)} HT d'honoraires à venir.</p>` : ""}
    </section>
    <section class="card"><h2>Vos biens</h2>
      <div class="etapes-tdb">${Object.entries(d.etapes).map(([k, n]) => `<div class="${n ? "" : "vide-etape"}"><strong>${n}</strong><span>${esc(d.libelles[k])}</span></div>`).join("")}</div>
    </section>
    <section class="card"><div class="dossier-top"><h2>Temps gagné ce mois-ci</h2><strong class="pct">${heures(d.temps_mois.minutes)}</strong></div>
      ${d.temps_mois.detail.map((x) => `<div class="m-ligne"><span>${esc(x.libelle)} × ${x.nombre}</span><strong>${heures(x.minutes)}</strong></div>`).join("") || '<p class="muted">Rien encore ce mois-ci.</p>'}
      <p class="small muted">Depuis le début : <strong>${heures(d.temps_total.minutes)}</strong>. Estimation : nombre d'actions faites automatiquement × temps habituel de chaque tâche faite à la main (barème de l'offre Synapse), à confirmer en conditions réelles.</p>
    </section>`;
};

export { state };
