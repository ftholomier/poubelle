// Espace client : signature en ligne (code par e-mail + signature au doigt) et dépôt des pièces en photo.
import { PadSignature } from "../js/pad.js";

const token = new URLSearchParams(location.search).get("t");
const post = async (action, body, form) => {
  const r = await fetch(`?t=${encodeURIComponent(token)}&a=${action}`, {
    method: "POST",
    headers: form ? { "X-Requested-With": "espace" } : { "X-Requested-With": "espace", "Content-Type": "application/json" },
    body: form || JSON.stringify(body),
  });
  const d = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(d.error || `Erreur ${r.status}`);
  return d;
};
window.espacePost = post;

// ---------- Signature ----------
document.querySelectorAll(".es-signer").forEach((bloc) => {
  const doc = bloc.dataset.doc, signataire = bloc.dataset.signataire;
  const msg = bloc.querySelector(".es-msg");
  const go = bloc.querySelector(".es-go");
  let pad = null, etape = "lecture";
  const dire = (t, cls = "") => ((msg.textContent = t), (msg.className = `es-msg ${cls}`));
  const demanderCode = async () => {
    try {
      const r = await post("code", { doc, signataire });
      if (r.envoye) {
        bloc.querySelector(".es-code").hidden = false;
        dire("Un code vient de vous être envoyé par e-mail.", "ok");
      }
    } catch (e) {
      dire(e.message, "erreur");
    }
  };
  bloc.querySelector(".es-renvoyer").onclick = demanderCode;
  bloc.querySelector(".es-effacer").onclick = () => pad?.effacer();
  go.onclick = async () => {
    if (!bloc.querySelector(".es-lu").checked) return dire("Cochez la case après avoir lu le document.", "erreur");
    if (etape === "lecture") {
      etape = "signature";
      bloc.querySelector(".es-pad-zone").hidden = false;
      pad = new PadSignature(bloc.querySelector(".es-pad"));
      go.textContent = "✍️ Valider ma signature";
      dire("");
      await demanderCode();
      return;
    }
    const image = pad.png();
    if (!image) return dire("Signez dans le cadre avec votre doigt.", "erreur");
    go.disabled = true;
    try {
      await post("signer", { doc, signataire, image, code: bloc.querySelector(".es-code-in").value.trim() });
      bloc.innerHTML = '<span class="es-tag">Signé</span><h2>Merci, c\'est signé.</h2><p>Vous recevrez votre exemplaire signé par e-mail dès que toutes les signatures seront recueillies.</p>';
    } catch (e) {
      dire(e.message, "erreur");
      go.disabled = false;
    }
  };
});

// ---------- Dépôt des pièces ----------
document.querySelectorAll("input[data-piece]").forEach((input) => {
  input.onchange = async () => {
    const f = input.files[0];
    if (!f) return;
    const msg = document.getElementById("msg-pieces");
    msg.className = "es-msg";
    msg.textContent = "Envoi et lecture du document…";
    const fd = new FormData();
    fd.append("fichier", f);
    fd.append("cle", input.dataset.piece);
    try {
      await post("piece", null, fd);
      location.reload();
    } catch (e) {
      msg.className = "es-msg erreur";
      msg.textContent = e.message;
    }
  };
});
